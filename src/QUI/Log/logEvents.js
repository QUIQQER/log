(function () {
    "use strict";

    var loadQUI = function () {
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

                        Ajax.post("package_quiqqer_log_ajax_logJsError", false, {
                            "package"    : "quiqqer/log",
                            errMsg       : msg,
                            errUrl       : url,
                            errLinenumber: linenumber,
                            browser      : navigator.userAgent.toString()
                        });
                    });
                });
            });
        }
    });
})();
