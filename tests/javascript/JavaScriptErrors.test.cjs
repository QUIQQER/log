const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

const packageRoot = path.resolve(__dirname, '../..');
const rendered = execFileSync('php', ['-r', `
    require $argv[1] . '/src/QUI/Log/JavaScriptErrorFilter.php';
    require $argv[1] . '/src/QUI/Log/JavaScriptErrorLogger.php';
    echo QUI\\Log\\JavaScriptErrorLogger::getScript();
`, packageRoot], {encoding: 'utf8'});
const source = rendered.replace(/^<script type="text\/javascript">/, '').replace(/<\/script>$/, '');
const logMethod = 'package_quiqqer_log_ajax_logJsError';
const walletError = "TypeError: undefined is not an object (evaluating 'window.ethereum.selectedAddress = undefined')";

async function createLogger({whenQuiLoaded, onPost} = {}) {
    const listeners = [];
    const requests = [];
    const timers = new Map();
    const windowEvents = new Map();
    const documentEvents = new Map();
    let timerId = 0;
    const QUI = {addEvent(name, handler) {
        assert.equal(name, 'onError');
        listeners.push(handler);
    }};
    const Ajax = {
        $onprogress: {
            product: {getAttribute: () => 'getProductData'},
            log: {getAttribute: () => logMethod}
        },
        post(method, callback, options) {
            requests.push({method, callback, ...options});
            if (onPost) {
                onPost(...listeners);
            }
        }
    };
    const window = {addEventListener: (name, handler) => windowEvents.set(name, handler)};
    const document = {
        visibilityState: 'visible',
        addEventListener: (name, handler) => documentEvents.set(name, handler)
    };
    const context = vm.createContext({
        window,
        document,
        whenQuiLoaded,
        navigator: {userAgent: 'Safari Brave'},
        console: {error() {}},
        setTimeout(handler, delay) {
            assert.equal(delay, 1000);
            timers.set(++timerId, handler);
            return timerId;
        },
        clearTimeout(id) { timers.delete(id); },
        require(dependencies, callback) {
            assert.deepEqual(Array.from(dependencies), ['qui/QUI', 'Ajax']);
            callback(QUI, Ajax);
        }
    });
    const install = async () => {
        vm.runInContext(source, context);
        await Promise.resolve();
    };
    await install();

    return {
        requests,
        listeners,
        Ajax,
        document,
        install,
        emit(...args) { listeners.forEach(listener => listener(...args)); },
        flush() { Array.from(timers.values()).forEach(handler => handler()); },
        pagehide() { windowEvents.get('pagehide')(); },
        visibilitychange() { documentEvents.get('visibilitychange')(); },
        get timerCount() { return timers.size; }
    };
}

test('wallet errors are filtered before requests, timers or report slots are used', async () => {
    const logger = await createLogger();
    for (let index = 0; index < 100; index++) {
        logger.emit(walletError, '/product', 1);
    }
    assert.equal(logger.timerCount, 0);
    logger.emit('Real error', '/basket.js', 42);
    logger.flush();
    assert.equal(logger.requests.length, 1);
    assert.equal(logger.requests[0].errMsg, 'Real error');
});

test('repeated errors are grouped and their count and original AJAX context are retained', async () => {
    const logger = await createLogger();
    for (let index = 0; index < 9; index++) {
        logger.emit('Script error.', '', 0);
    }
    assert.equal(logger.requests.length, 0);
    assert.equal(logger.timerCount, 1);
    logger.Ajax.$onprogress = {};
    logger.flush();
    assert.equal(logger.requests.length, 1);
    const report = logger.requests[0];
    assert.equal(report.occurrences, 9);
    assert.deepEqual(JSON.parse(report.context), [{method: 'getProductData'}]);
    assert.equal(report.errLineNumber, 0);
    assert.equal(report.showError, false);
    assert.equal(report.method, logMethod);
});

test('the page limit applies across batches and duplicates still increment a reserved report', async () => {
    const logger = await createLogger();
    for (let batch = 0; batch < 3; batch++) {
        for (let index = 0; index < 10; index++) {
            logger.emit(`Error ${batch}-${index}`, '/basket.js', index);
            logger.emit(`Error ${batch}-${index}`, '/basket.js', index);
        }
        logger.flush();
    }
    assert.equal(logger.requests.length, 20);
    assert.ok(logger.requests.every(report => report.occurrences === 2));
    assert.equal(logger.timerCount, 0);
});

test('a single burst cannot reserve more reports than the page limit', async () => {
    const logger = await createLogger();
    for (let index = 0; index < 100; index++) {
        logger.emit(`Error ${index}`, '/basket.js', index);
    }
    logger.emit('Error 0', '/basket.js', 0);
    logger.flush();
    assert.equal(logger.requests.length, 20);
    assert.equal(logger.requests[0].occurrences, 2);
});

test('column and stack distinguish errors and are passed to PHP', async () => {
    const logger = await createLogger();
    const error = {stack: 'TypeError: failed\n    at basket.js:42:16'};
    logger.emit('TypeError: failed', '/basket.js', 42, 16, error);
    logger.emit('TypeError: failed', '/basket.js', 42, 17, error);
    logger.flush();
    assert.equal(logger.requests.length, 2);
    assert.equal(logger.requests[0].errColumnNumber, 16);
    assert.equal(logger.requests[0].errStack, error.stack);
    assert.equal(logger.requests[1].errColumnNumber, 17);
});

test('legacy errors without column or Error object remain reportable', async () => {
    const logger = await createLogger();
    logger.emit('RequireJS failed');
    logger.flush();
    assert.equal(logger.requests[0].errColumnNumber, 0);
    assert.equal(logger.requests[0].errStack, '');
    assert.equal(logger.requests[0].errUrl, '');
});

test('duplicate script installation registers only one logger and shares its page budget', async () => {
    const logger = await createLogger();
    await logger.install();
    assert.equal(logger.listeners.length, 1);
    logger.emit('Error', '/basket.js', 1);
    logger.flush();
    assert.equal(logger.requests.length, 1);
});

test('a reporting error cannot synchronously create further reports', async () => {
    const logger = await createLogger({onPost: listener => listener('Reporting failed', '/Ajax.js', 1)});
    logger.emit('Original error', '/basket.js', 1);
    logger.flush();
    logger.flush();
    assert.equal(logger.requests.length, 1);
    assert.equal(logger.timerCount, 0);
});

test('pagehide and hidden visibility flush pending reports without sending them twice', async () => {
    const logger = await createLogger();
    logger.emit('Error', '/basket.js', 1);
    logger.document.visibilityState = 'hidden';
    logger.visibilitychange();
    logger.pagehide();
    logger.flush();
    assert.equal(logger.requests.length, 1);
    assert.equal(logger.timerCount, 0);
});

test('visible state changes do not prematurely flush the aggregation window', async () => {
    const logger = await createLogger();
    logger.emit('Error', '/basket.js', 1);
    logger.visibilitychange();
    assert.equal(logger.requests.length, 0);
    logger.flush();
    assert.equal(logger.requests.length, 1);
});

test('registration waits for QUI readiness', async () => {
    let ready;
    const readiness = new Promise(resolve => { ready = resolve; });
    const logger = await createLogger({whenQuiLoaded: () => readiness});
    assert.equal(logger.listeners.length, 0);
    ready();
    await readiness;
    assert.equal(logger.listeners.length, 1);
});
