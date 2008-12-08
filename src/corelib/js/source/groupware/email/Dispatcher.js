/**
 * conjoon
 * (c) 2002-2009 siteartwork.de/conjoon.org
 * licensing@conjoon.org
 *
 * $Author$
 * $Id$
 * $Date$
 * $Revision$
 * $LastChangedDate$
 * $LastChangedBy$
 * $URL$
 */

Ext.namespace('com.conjoon.groupware.email');

/**
 * Singleton that provides convinient methods for saving/sending emails.
 *
 * @class com.conjoon.groupware.email.Dispatcher
 * @singleton
 */
com.conjoon.groupware.email.Dispatcher = function() {

    var _onBulkSendSuccess = function(response, options)
    {
        var data = com.conjoon.groupware.ResponseInspector.isSuccess(response);

        if (data == null) {
            _onBulkSendFailure(response, options);
            return;
        }

        var sentItems = data.sentItems;

        if (sentItems.length == 0) {
            _onBulkSendFailure(response, options);
            return;
        }

        var sentData  = [];
        for (var i = 0, len = sentItems.length; i < len; i++) {
            sentData.push(com.conjoon.util.Record.convertTo(
                com.conjoon.groupware.email.EmailItemRecord,
                sentItems[i],
                sentItems[i].id
            ));
        }

        var cris = [];
        var contextReferencedItems = data.contextReferencedItems;
        for (var i = 0, len = contextReferencedItems.length; i < len; i++) {
            cris.push(com.conjoon.util.Record.convertTo(
                com.conjoon.groupware.email.EmailItemRecord,
                contextReferencedItems[i],
                contextReferencedItems[i].id
            ));
        }

        Ext.ux.util.MessageBus.publish('com.conjoon.groupware.email.Smtp.bulkSent', {
           emailItems             : options.emailItems,
           sentItems              : sentData,
           contextReferencedItems : cris
        });

    };

    var _onBulkSendFailure = function(response, options)
    {
        com.conjoon.groupware.ResponseInspector.handleFailure(response, {
            title : com.conjoon.Gettext.gettext("Error - Could not send messages")
        });

        Ext.ux.util.MessageBus.publish('com.conjoon.groupware.email.Smtp.bulkSentFailure', {
            emailItems : options.emailItems
        });
    };

    var _onManageDraftSuccess = function(response, options, type)
    {
        var data = com.conjoon.groupware.ResponseInspector.isSuccess(response);

        if (data == null) {
            _onManageDraftFailure(response, options, type);
            return;
        }

        var itemRecord = com.conjoon.util.Record.convertTo(
            com.conjoon.groupware.email.EmailItemRecord,
            data.item,
            data.item.id
        );

        var subject = '';

        var pubObject = {
            referencedItem : options.referencedItem,
            draft          : options.draft,
            options        : options.additionalOptions,
            itemRecord     : itemRecord
        };

        switch (type) {
            case 'send':
                subject = 'com.conjoon.groupware.email.Smtp.emailSent';
                var cri = null;
                if (data.contextReferencedItem) {
                    cri = com.conjoon.util.Record.convertTo(
                        com.conjoon.groupware.email.EmailItemRecord,
                        data.contextReferencedItem,
                        data.contextReferencedItem.id
                    );
                    Ext.apply(pubObject, {
                        contextReferencedItem : cri
                    });
                }
            break;

            case 'outbox':
                subject = 'com.conjoon.groupware.email.outbox.emailMove';
            break;

            case 'edit':
                subject = 'com.conjoon.groupware.email.editor.draftSave';
                var emailRecord = com.conjoon.util.Record.convertTo(
                    com.conjoon.groupware.email.EmailRecord,
                    data.emailRecord,
                    data.emailRecord.id
                );
                Ext.apply(pubObject, {
                    emailRecord : emailRecord
                });
            break;
        }

        Ext.ux.util.MessageBus.publish(subject, pubObject);
    };

    var _onManageDraftFailure = function(response, options, type)
    {
        var subject = '';
        var title   = '';

        switch (type) {
            case 'send':
                title   = com.conjoon.Gettext.gettext("Error - Could not send message.");
                subject = 'com.conjoon.groupware.email.Smtp.emailSentFailure';
            break;

            case 'outbox':
                title   = com.conjoon.Gettext.gettext("Error - Could not move message to the outbox.");
                subject = 'com.conjoon.groupware.email.outbox.emailMoveFailure';
            break;

            case 'edit':
                title   = com.conjoon.Gettext.gettext("Error - Could not save draft.");
                subject = 'com.conjoon.groupware.email.editor.draftSaveFailure';
            break;
        }

        com.conjoon.groupware.ResponseInspector.handleFailure(response, {
            title : title
        });

        Ext.ux.util.MessageBus.publish(subject, {
            options        : options.additionalOptions,
            draft          : options.draft,
            referencedItem : options.referencedItem,
            response       : response
        });
    };

    var _manageDraft = function(draft, referencedItem, options, type)
    {
        // check if any valid email-addresses have been submitted
        if ((type == 'send' || type == 'outbox') &&
            (draft.get('to') == '' && draft.get('cc') == '' && draft.get('bcc') == '')) {
            var msg  = Ext.MessageBox;

            msg.show({
                title   : com.conjoon.Gettext.gettext("Error - specify recipient(s)"),
                msg     : com.conjoon.Gettext.gettext("Please specify one or more recipients for this message."),
                buttons : msg.OK,
                icon    : msg.WARNING,
                scope   : this,
                cls     :'com-conjoon-msgbox-warning',
                width   : 400
            });

            return;
        }

        var subject     = '';
        var url         = '';

        switch (type) {
            case 'send':
                subject     = 'com.conjoon.groupware.email.Smtp.beforeEmailSent';
                url         = '/groupware/email/send/format/json';
            break;

            case 'outbox':
                subject     = 'com.conjoon.groupware.email.outbox.beforeEmailMove';
                url         = '/groupware/email/move.to.outbox/format/json';
            break;

            case 'edit':
                subject     = 'com.conjoon.groupware.email.editor.beforeDraftSave';
                url         = '/groupware/email/save.draft/format/json';
            break;
        }

        Ext.ux.util.MessageBus.publish(subject, {
            draft          : draft,
            referencedItem : referencedItem,
            options        : options
        });

        var opts = {
            additionalOptions : options,
            draft             : draft,
            referencedItem    : referencedItem,
            url               : url,
            params            : draft.data,
            success           : function(response, options) {
                _onManageDraftSuccess(response, options, type);
            },
            failure           : function(response, options) {
                _onManageDraftFailure(response, options, type);
            },
            disableCaching    : true
        };

        Ext.Ajax.request(opts);
    };

    return {

        /**
         * Sends a request to the server to send the specified emailItems which
         * are currently pending in the outbox folder.
         *
         * @param {Array} emailItems An array of {com.conjoon.groupware.email.EmailItemRecord}s
         * @param {Number} date unix timestamp. If specified, the supplied argument
         * will be used for setting the "date"-header in the emails to send.
         */
        sendPendingEmails : function(emailItems, date)
        {
            if (!Ext.isArray(emailItems) || emailItems.length == 0) {
                return;
            }

            Ext.ux.util.MessageBus.publish('com.conjoon.groupware.email.Smtp.beforeBulkSent', {
               emailItems : emailItems
            });

            var ids = [];
            for (var i = 0, len = emailItems.length; i < len; i++) {
                ids.push(emailItems[i].get('id'));
            }

            var params = {
                ids  : Ext.encode(ids)
            };

            if (date) {
                Ext.apply(params, {
                    date : date
                });
            }

            var opts = {
                emailItems     : emailItems,
                url            : '/groupware/email/bulk.send/format/json',
                params         : params,
                success        : _onBulkSendSuccess,
                failure        : _onBulkSendFailure,
                disableCaching : true
            };

            Ext.Ajax.request(opts);
        },

        /**
         * Sends the specified data as an email message.
         *
         * @param {com.conjoon.groupware.email.data.Draft} draft
         * @param {com.conjoon.groupware.email.EmailItemRecord} referencedItem
         * @param {Object} options additional set of options to be used for the
         * Ext.Ajax.request.
         */
        sendEmail : function(draft, referencedItem, options)
        {
            _manageDraft(draft, referencedItem, options, 'send');
        },

        /**
         * Moves the specified draft into the outbox folder.
         *
         * @param {com.conjoon.groupware.email.data.Draft} draft
         * @param {com.conjoon.groupware.email.EmailItemRecord} referencedItem
         * @param {Object} options additional set of options to be used for the
         * Ext.Ajax.request.
         */
        moveDraftToOutbox : function(draft, referencedItem, options)
        {
            _manageDraft(draft, referencedItem, options, 'outbox');
        },

        /**
         * Saves the specified draft.
         *
         * @param {com.conjoon.groupware.email.data.Draft} draft
         * @param {com.conjoon.groupware.email.EmailItemRecord} referencedItem
         * @param {Object} options additional set of options to be used for the
         * Ext.Ajax.request.
         */
        saveDraft : function(draft, referencedItem, options)
        {
            _manageDraft(draft, referencedItem, options, 'edit');
        }

    };

}();