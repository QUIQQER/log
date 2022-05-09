(function () {
    "use strict";

    let loadQUI = function () {
        return Promise.resolve();
    };

    if (typeof whenQuiLoaded === 'function') {
        loadQUI = whenQuiLoaded;
    }

    loadQUI().then(function () {
        if (typeof require !== "undefined") {
            require(["qui/QUI"], function (QUI) {
                QUI.addEvent("onError", function (msg, url, linenumber) {
                    console.error(
                        "Message " + msg + "\n" +
                        "URL " + url + "\n" +
                        "Linenumber " + linenumber
                    );

                    require(["Ajax"], function (Ajax) {
                        if (typeof Ajax === "undefined") {
                            return;
                        }

                        const context = [];

                        for (let i in Ajax.$onprogress) {
                            if (!Ajax.$onprogress.hasOwnProperty(i)) {
                                continue;
                            }

                            context.push({
                                method: Ajax.$onprogress[i].getAttribute("_rf")
                            });
                        }

                        Ajax.post("package_quiqqer_log_ajax_logJsError", false, {
                            "package"    : "quiqqer/log",
                            errMsg       : msg,
                            errUrl       : url,
                            errLinenumber: linenumber,
                            browser      : navigator.userAgent.toString(),
                            context      : JSON.encode(context)
                        });
                    });
                });
            });
        }
    });
})();
