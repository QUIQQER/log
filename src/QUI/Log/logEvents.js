(function(settings) {
    'use strict';

    const loadQUI = typeof whenQuiLoaded === 'function' ? whenQuiLoaded : () => Promise.resolve();

    loadQUI().then(function() {
        if (typeof require === 'undefined') {
            return;
        }

        require(['qui/QUI', 'Ajax'], function(QUI, Ajax) {
            const registration = Symbol.for('quiqqer.log.jsErrors');

            if (!Ajax || window[registration]) {
                return;
            }

            window[registration] = true;

            const method = 'package_quiqqer_log_ajax_logJsError';
            const pending = new Map();
            let reportsSent = 0;
            let flushTimer = null;
            let sending = false;

            function flush() {
                clearTimeout(flushTimer);
                flushTimer = null;

                const reports = Array.from(pending.values());
                pending.clear();

                // Reserve the whole batch before sending: reporting can itself trigger an error.
                reportsSent += reports.length;
                sending = true;

                try {
                    for (const report of reports) {
                        Ajax.post(method, false, {
                            'package': 'quiqqer/log',
                            ...report,
                            browser: navigator.userAgent.toString(),
                            context: JSON.stringify(report.context),
                            showError: false
                        });
                    }
                } finally {
                    sending = false;
                }
            }

            QUI.addEvent('onError', function(msg, url, lineNumber, columnNumber, error) {
                if (sending) {
                    return;
                }

                msg = String(msg ?? '');
                url = String(url ?? '');
                lineNumber = lineNumber ?? 0;
                columnNumber = columnNumber ?? 0;
                const stack = typeof error?.stack === 'string' ? error.stack : '';

                if ((!msg && !url && !lineNumber) || settings.ignoredMessages.some(value => msg.includes(value))) {
                    return;
                }

                const key = JSON.stringify([msg, url, lineNumber, columnNumber, stack]);
                const existing = pending.get(key);

                if (existing) {
                    existing.occurrences++;
                    return;
                }

                if (reportsSent + pending.size >= settings.maxReports) {
                    return;
                }

                const context = [];

                for (const request of Object.values(Ajax.$onprogress)) {
                    const requestMethod = request.getAttribute('_rf');

                    if (requestMethod && requestMethod !== method) {
                        context.push({method: requestMethod});
                    }
                }

                pending.set(key, {
                    errMsg: msg,
                    errUrl: url,
                    errLineNumber: lineNumber,
                    errColumnNumber: columnNumber,
                    errStack: stack,
                    occurrences: 1,
                    context: context
                });

                console.error('Message ' + msg + '\nURL ' + url + '\nLinenumber ' + lineNumber);

                if (flushTimer === null) {
                    flushTimer = setTimeout(flush, settings.aggregationDelay);
                }
            });

            window.addEventListener('pagehide', flush);
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    flush();
                }
            });
        });
    });
})
