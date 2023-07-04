/**
 * Log Manager
 *
 * @module package/quiqqer/log/bin/Manager
 * @author www.pcsg.de (Henning Leutz)
 */

define('package/quiqqer/log/bin/Manager', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'controls/grid/Grid',
    'Ajax',
    'Locale',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Separator',
    'qui/controls/windows/Confirm',

    'css!package/quiqqer/log/bin/Manager.css'

], function (QUI, Panel, Grid, Ajax, Locale, QUIButton, QUIButtonSeparator, QUIConfirm) {
    "use strict";

    const lg = 'quiqqer/log';

    return new Class({

        Extends: Panel,
        Type: 'package/quiqqer/log/bin/Manager',

        Binds: [
            'getLogs',
            'resize',
            'refreshFile',
            'deleteActiveLog',
            'closeActiveLog',
            'downloadActiveLog',
            '$onCreate',
            '$onResize',
            '$onDestroy',
            '$btnOpenLog',
            '$gridRefresh',
            '$gridClick',
            '$gridDblClick'
        ],

        options: {
            file: '',
            page: 1,
            limit: 20,
            search: '',
            'site-width': 220
        },

        initialize: function (options) {
            // defaults
            this.setAttribute('title', Locale.get(lg, 'logs.panel.title'));
            this.setAttribute('icon', 'fa fa-terminal');

            this.parent(options);

            this.$Fx = null;
            this.$Search = null;
            this.$Grid = null;
            this.$GridContainer = null;


            this.$openLog = false;
            this.$file = false;

            this.addEvents({
                onCreate: this.$onCreate,
                onDestroy: this.$onDestroy,
                onResize: this.$onResize
            });
        },

        /**
         * Asking for logs, show the log list
         */
        getLogs: function () {
            const self = this;

            this.Loader.show();

            const sortOn = this.$Grid.options.sortOn,
                sortBy = this.$Grid.options.sortBy;

            Ajax.get('package_quiqqer_log_ajax_get', function (result) {
                // open buttons
                for (let i = 0, len = result.data.length; i < len; i++) {
                    result.data[i].open = {
                        image: 'fa fa-code',
                        file: result.data[i].file,

                        alt: Locale.get(lg, 'logs.panel.btn.open.log', {
                            date: result.data[i].mdate
                        }),

                        title: Locale.get(lg, 'logs.panel.btn.open.log', {
                            date: result.data[i].mdate
                        }),

                        events: {
                            onClick: self.$btnOpenLog
                        }
                    };
                }

                self.$Grid.setData(result);
                self.Loader.hide();
            }, {
                'package': 'quiqqer/log',
                page: this.getAttribute('page'),
                limit: this.getAttribute('limit'),
                search: this.getAttribute('search'),
                sortOn: sortOn,
                sortBy: sortBy
            });
        },

        /**
         * Open a log file
         *
         * @param {String} file - name of the log
         */
        openLog: function (file) {
            if (!this.$Fx) {
                return;
            }

            const Control = this;

            Control.Loader.show();

            Control.getButtons('closeLog').enable();

            Control.$openLog = true;
            Control.$file = file;

            Control.setAttribute(
                'title',
                Locale.get(lg, 'logs.panel.log.title', {
                    file: file
                })
            );

            Control.$Fx.animate({
                width: Control.getAttribute('site-width')
            }, {
                callback: function () {
                    const Body = Control.getContent(),
                        Parent = Body.getParent();

                    let MessageOverlay = $('qui-logs-message');

                    if (!MessageOverlay) {
                        MessageOverlay = new Element('div#qui-logs-message.messages-message.message-attention');
                        MessageOverlay.hide();
                        MessageOverlay.inject(Parent);
                    }

                    if (!Parent.getElement('.qui-logs-file')) {
                        new Element('div.qui-logs-file').inject(Parent);
                    }

                    Control.refreshFile();

                    MessageOverlay.set('text', Locale.get(lg, 'logs.panel.message.trimmed'));
                    MessageOverlay.show();

                    MessageOverlay.position({
                        relativeTo: Parent.getElement('.qui-logs-file'),
                        position: "topLeft",
                        offset: {x: 0, y: -3}
                    });
                }
            });
        },

        /**
         * Delete a log
         *
         * @param {String} file - name of the log
         * @param {Function} [callback] - callback function
         */
        deleteLog: function (file, callback) {
            Ajax.get('package_quiqqer_log_ajax_delete', function () {
                if (typeof callback !== 'undefined') {
                    callback();
                }
            }, {
                'package': 'quiqqer/log',
                file: file
            });
        },

        /**
         * Delete the active log
         */
        deleteActiveLog: function () {
            const self = this,
                sel = this.$Grid.getSelectedData();

            new QUIConfirm({
                title: Locale.get(lg, 'logs.panel.delete.window.title', {
                    file: sel[0].file
                }),
                icon: 'fa fa-remove',
                text: Locale.get(lg, 'logs.panel.delete.window.text'),
                events: {
                    onSubmit: function () {
                        self.Loader.show();

                        self.deleteLog(sel[0].file, function () {
                            self.getLogs();
                        });
                    }
                }
            }).open();
        },

        /**
         * Download the active log
         */
        downloadActiveLog: function () {
            const self = this;

            // Check if the user is allowed to download logs
            Ajax.get('package_quiqqer_log_ajax_canUserDownloadLogs', function (canUserDownloadLogs) {
                // If user is not allowed to download logs...
                if (!canUserDownloadLogs) {
                    // ...display no-permission error on the button
                    QUI.getMessageHandler(function (MH) {
                        MH.addError(
                            Locale.get(lg, 'logs.download.message.permission'),
                            self.getButtons('download').getElm()
                        );
                    });

                    return;
                }

                // Create an iframe that downloads the log
                const data = self.$Grid.getSelectedData(),
                    log = data[0].file,
                    downloadFile = URL_OPT_DIR + 'quiqqer/log/bin/downloadLog.php?log=' + encodeURIComponent(log),
                    iframeId = Math.floor(Date.now() / 1000);

                new Element('iframe', {
                    id: 'download-iframe-' + iframeId,
                    src: downloadFile,
                    styles: {
                        left: -1000,
                        height: 10,
                        position: 'absolute',
                        top: -1000,
                        width: 10
                    },
                    'data-iframeid': iframeId
                }).inject(document.body);
            }, {
                'package': 'quiqqer/log'
            });
        },

        /**
         * Closes the active log (panel)
         */
        closeActiveLog: function () {

            const Control = this,
                Body = Control.getContent(),
                Parent = Body.getParent();

            Control.Loader.show();

            Control.$openLog = false;
            Control.$file = '';

            // Log Content Panel
            Parent.getElement('.qui-logs-file').destroy();

            // Log Overlay Message
            Parent.getElement('#qui-logs-message').destroy();

            // Resize Log Table to full width
            Control.$onResize();
            Control.getContent().setStyle('width', '100%');

            Control.getButtons('closeLog').disable();

            Control.Loader.hide();
        },

        /**
         * Refresh the current file
         *
         * @return {Object} this (controls/logs/Panel)
         */
        refreshFile: function () {
            if (!this.$file) {
                return this;
            }

            const Control = this,
                File = this.getBody().getParent().getElement('.qui-logs-file');

            this.Loader.show();

            Ajax.get('package_quiqqer_log_ajax_file', function (result) {
                if (!result) {
                    document.getElements('.qui-logs-message').set('text', Locale.get(lg, 'logs.panel.message.permission'));

                    Control.Loader.hide();
                    return false;
                }

                File.set(
                    'html',
                    '<pre id="qui-logs-file-data" class="box language-bash" style="margin: 0;">' + result.data + '</pre>'
                );

                if (result.isLogTrimmed) {
                    document.getElements('.qui-logs-message').show();
                } else {
                    document.getElements('.qui-logs-message').hide();
                }

                Control.Loader.hide();
                Control.refresh();

                const LogFileData = File.getElement('#qui-logs-file-data');
                LogFileData.scrollTop = LogFileData.scrollHeight;
            }, {
                'package': 'quiqqer/log',
                file: this.$file
            });
        },

        /**
         * event : on create
         * build the panel
         */
        $onCreate: function () {
            const Control = this;

            this.$GridContainer = new Element('div', {
                'class': 'qui-logs-container'
            }).inject(this.getContent());

            this.$Fx = moofx(this.getContent());

            this.$Grid = new Grid(this.$GridContainer, {
                columnModel: [{
                    header: '&nbsp;',
                    dataIndex: 'open',
                    dataType: 'button',
                    width: 60
                }, {
                    header: Locale.get(lg, 'logs.panel.log.file'),
                    dataIndex: 'file',
                    dataType: 'string',
                    width: 200
                }, {
                    header: Locale.get('quiqqer/system', 'e_date'),
                    dataIndex: 'mdate',
                    dataType: 'date',
                    width: 200
                }],
                sortBy: 'DESC',
                sortOn: 'mdate',
                pagination: true,
                onrefresh: this.$gridRefresh,
                serverSort: true
            });

            this.$Grid.addEvents({
                onClick: this.$gridClick,
                onDblClick: this.$gridDblClick
            });

            this.$Search = new Element('input', {
                'class': 'qui-logs-search',
                placeholder: Locale.get(lg, 'logs.panel.search.placeholder'),
                events: {
                    keyup: function (event) {
                        if (event.key === 'enter') {
                            Control.getButtons('search').onclick();
                        }
                    }
                }
            });

            this.getButtonBar().appendChild(this.$Search);

            this.addButton(
                new QUIButton({
                    name: 'search',
                    image: 'fa fa-search',
                    alt: Locale.get(lg, 'logs.panel.search.btn.start.alt'),
                    title: Locale.get(lg, 'logs.panel.search.btn.start.title'),
                    events: {
                        onClick: function () {
                            Control.setAttribute(
                                'search',
                                Control.$Search.value
                            );

                            Control.getLogs();
                        }
                    }
                })
            );

            this.addButton(
                new QUIButtonSeparator()
            );


            this.addButton(
                new QUIButton({
                    name: 'refresh',
                    text: Locale.get(lg, 'logs.panel.btn.refresh'),
                    textimage: 'fa fa-refresh',
                    disabled: true,
                    events: {
                        onClick: this.refreshFile
                    }
                })
            );

            this.addButton(
                new QUIButtonSeparator()
            );

            this.addButton(
                new QUIButton({
                    name: 'delete',
                    text: Locale.get(lg, 'logs.panel.btn.delete.marked'),
                    textimage: 'fa fa-trash',
                    disabled: true,
                    events: {
                        onClick: this.deleteActiveLog
                    }
                })
            );

            this.addButton(
                new QUIButtonSeparator()
            );

            this.addButton(
                new QUIButton({
                    name: 'download',
                    text: Locale.get(lg, 'logs.panel.btn.download.marked'),
                    textimage: 'fa fa-download',
                    disabled: true,
                    events: {
                        onClick: this.downloadActiveLog
                    }
                })
            );


            this.addButton(
                new QUIButton({
                    name: 'closeLog',
                    icon: 'fa fa-close',
                    title: Locale.get(lg, 'logs.panel.btn.close.log'),
                    disabled: true,
                    events: {
                        onClick: this.closeActiveLog
                    },
                    styles: {
                        float: 'right'
                    }
                })
            );


            //this.resize.delay( 200 );
            this.getLogs.delay(100, this);
        },

        /**
         * event : on resize
         * resize the panel and all elements
         */
        $onResize: function () {
            if (!this.getBody()) {
                return;
            }

            let size, height, width;

            const Body = this.getBody(),
                Header = this.getHeader(),
                Parent = Body.getParent(),
                File = Parent.getElement('.qui-logs-file');

            size = Parent.getSize();
            height = size.y - Header.getSize().y - 50;

            if (this.getButtonBar()) {
                height = height - this.getButtonBar().getElm().getSize().y;
            }

            // file display
            if (this.$openLog) {
                Body.setStyle('width', this.getAttribute('site-width'));

                this.getButtons('refresh').enable();

                this.$Grid.setWidth(180);
                this.$Grid.setHeight(height);

                if (File) {
                    width = this.getAttribute('site-width') + 20;

                    File.setStyles({
                        height: height,
                        width: size.x - width
                    });

                    File.getElement('pre').setStyles({
                        height: height,
                        width: size.x - width
                    });
                }

                const LogMessage = document.getElements('.qui-logs-message');

                if (LogMessage) {
                    LogMessage.setStyles({
                        height: 40,
                        width: size.x - width
                    });
                }

                return;
            }

            // only log listing
            if (this.getButtons('refresh')) {
                this.getButtons('refresh').disable();
            }

            this.$Grid.setWidth(size.x - 40);
            this.$Grid.setHeight(height);
        },

        /**
         * event : on destroy
         */
        $onDestroy: function () {
            this.$Grid.destroy();
        },

        /**
         * Click on the log button to open the log
         *
         * @param {Object} Btn - qui/controls/buttons/Button
         */
        $btnOpenLog: function (Btn) {
            this.openLog(Btn.getAttribute('file'));
        },

        /**
         * event : grid refresh
         *
         * @param {Object} Gridinstance - controls/grid/Grid
         */
        $gridRefresh: function (Gridinstance) {
            this.setAttributes({
                limit: Gridinstance.getAttribute('perPage'),
                page: Gridinstance.getAttribute('page')
            });

            this.getLogs();
        },

        /**
         * event : on grid click
         *
         * @param {Object} data - Grid Data
         */
        $gridClick: function (data) {
            const len = data.target.selected.length,
                Delete = this.getButtons('delete'),
                Download = this.getButtons('download');

            Delete.disable();
            Download.disable();

            if (len) {
                Delete.enable();
                Download.enable();
            }
        },

        /**
         * event : on grid dbl click
         */
        $gridDblClick: function () {
            const sel = this.$Grid.getSelectedData();

            this.openLog(sel[0].file);
        }
    });
});
