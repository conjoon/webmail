/**
 * intrabuild
 * (c) 2002-2008 siteartwork.de/MindPatterns
 * license@siteartwork.de
 *
 * $Author$
 * $Id$
 * $Date$ 
 * $Revision$
 * $LastChangedDate$
 * $LastChangedBy$
 * $URL$ 
 */
 
/**
 * The EmailEditorManager Singleton allows for creating an instance of EmailForm
 * that will be reused for every email that will be written once the object was
 * instantiating. The form values will be stored in a set that maps to the 
 * individual panel id. Upon deactivatin/activating the values will be stored
 * and set depending which panel shows.
 *
 *
 */
Ext.namespace('de.intrabuild.groupware.email');

de.intrabuild.groupware.email.EmailEditorManager = function(){
    
    var contentPanel = null;
    
    var utilDom = de.intrabuild.util.Dom;
    
    var tabIdCount = 0;
    var tabCount   = 0;
    
    var controlBar  = null;
    var masterPanel = null;
    var form        = null;
    var activePanel = null;
    
    var subjectField   = null;
    var htmlEditor     = null;
    var accountField   = null;
    var recipientStore = null;
    var recipientsGrid = null;
	
    var messages = {
        loading : de.intrabuild.Gettext.gettext("Loading message..."),
        saving  : de.intrabuild.Gettext.gettext("Saving draft..."),
        sending : de.intrabuild.Gettext.gettext("Sending message..."),
        outbox  : de.intrabuild.Gettext.gettext("Moving message...")    
    };
    
    
    var formValues = [];
    
    var queue = [];
    
    var activePanelMasks = [];
    
    var createPanel = function(draftId, type)
    {
        if (form == null) {
            
            form = new de.intrabuild.groupware.email.EmailForm({
                id                : 'DOM:de.intrabuild.groupware.email.EmailEditor.form', 
                layout            : 'border',
                region            : 'center',
                border            : false,
                hideMode          : 'offsets',
                autoScroll        : false
            });
            form.on('render', function(){
                this.loadMask = new Ext.LoadMask(this.el.dom.id, {msg : messages.loading});
				this.loadMask.el.dom.style.zIndex = 1;
            }, form);
            form.on('initialize', onFormInitialize, de.intrabuild.groupware.email.EmailEditorManager);
			form.on('initializefailure', onFormInitializeFailure, de.intrabuild.groupware.email.EmailEditorManager);
            
            masterPanel = new Ext.Panel({
                id         : 'DOM:de.intrabuild.groupware.email.EmailEditor.masterTab', 
                layout     : 'fit',
                items      : [form],
                hideMode   : 'offsets',
                border     : false,
                autoScroll : false
            });      
            
            subjectField = form.subjectField;
            subjectField.on('render', function(){
                subjectField.el.on('keyup',    onSubjectValueChange, de.intrabuild.groupware.email.EmailEditorManager);
                subjectField.el.on('keydown',  onSubjectValueChange, de.intrabuild.groupware.email.EmailEditorManager);
                subjectField.el.on('keypress', onSubjectValueChange, de.intrabuild.groupware.email.EmailEditorManager);
            }, this);    
    
    		recipientsGrid = form.grid;
     		recipientsGrid.on('afteredit', onAfterEdit, de.intrabuild.groupware.email.EmailEditorManager);
    
    		accountField   = form.fromComboBox;
    		accountField.on('select', onAccountSelect, de.intrabuild.groupware.email.EmailEditorManager);
    		
            htmlEditor     = form.htmlEditor;
            htmlEditor.on('initialize' , function(){
            	var fly = Ext.fly(this.doc);
            	fly.addKeyListener([10, 13], onHtmlEditorEdit, 
            		de.intrabuild.groupware.email.EmailEditorManager);
            }, htmlEditor);
            
            recipientStore = form.gridStore;
            
            
            contentPanel.add(masterPanel);
            document.getElementById(contentPanel.id+'__'+masterPanel.id).style.display = 'none';
            contentPanel.setActiveTab(masterPanel);

        } 
        
        var panel = new Ext.Panel({
            id         : 'DOM:de.intrabuild.groupware.email.EmailEditor.tab_'+tabIdCount, 
            bodyStyle  : 'display:none',
            title      : de.intrabuild.Gettext.gettext("Loading..."),
            hideMode   : 'offsets',
            closable   : true,
            border     : false,
            iconCls    : 'de-intrabuild-groupware-email-EmailForm-icon',
            autoScroll : false
        });
        
        formValues[panel.id] = {
			signatureAttached : false,
			dirty		      : false,
            pending           : false,
            disabled          : true,
            subject           : "",
            message           : "",
            accountId         : null,
            sourceEditMode    : false,
            recipients        : []
        };
        
        var ajaxOptions = {
            url            : '/groupware/email/get.draft/format/json',
            disableCaching : true,
			panelId        : panel.id,
			params         : {
				id   : draftId || -1, 
				type : type || 'new'
			}, 
			success : onRecipientsLoad,
			failure : onRecipientsLoadException,
			scope   : de.intrabuild.groupware.email.EmailEditorManager
        };
        
        if (form.initialized) {
            Ext.Ajax.request(ajaxOptions);
        } else {
            queue.push(ajaxOptions);
        }
        
        registerToolbar();
        
        panel.on('deactivate', onDeactivatePanel, de.intrabuild.groupware.email.EmailEditorManager);
        panel.on('render',     onActivatePanel,   de.intrabuild.groupware.email.EmailEditorManager);
        panel.on('activate',   onActivatePanel,   de.intrabuild.groupware.email.EmailEditorManager);
        panel.on('destroy',    onDestroyPanel,    de.intrabuild.groupware.email.EmailEditorManager);        
        contentPanel.on('beforeremove',  onBeforeClose,    de.intrabuild.groupware.email.EmailEditorManager);        
        
        contentPanel.add(panel);
        contentPanel.setActiveTab(panel);
        
        tabCount++;
        tabIdCount++;
    };

    var onBeforeClose = function(container, component)
    {
        var id = component.el.dom.id;
        var grp = id.split('_');
        
        if (grp[0] == 'DOM:de.intrabuild.groupware.email.EmailEditor.tab') {
            
            if (!activePanel || activePanel.id != id) {
                container.setActiveTab(component);
            }
            
            if (formValues[id].pending) {
                return false;
            }
            
            recipientsGrid.stopEditing();
            
            if (formValues[id].dirty) {
	            var msg   = Ext.MessageBox;
	            msg.show({
	                title   : de.intrabuild.Gettext.gettext("Confirm - close message"),
	                msg     : de.intrabuild.Gettext.gettext("You did not send this message. Do you really want to close this message without saving?"),
	                buttons : msg.YESNO,
	                fn      : function(btn){
	                              if (btn == 'yes') {
	                                  contentPanel.un('beforeremove',  onBeforeClose,    de.intrabuild.groupware.email.EmailEditorManager);        
	                                  container.remove(component);
	                                  if (tabCount > 0) {
	                                    contentPanel.on('beforeremove',  onBeforeClose,    de.intrabuild.groupware.email.EmailEditorManager);        
	                                  }
	                              }
	                          },
	                icon    : msg.QUESTION,
	                cls     :'de-intrabuild-msgbox-question',
	                width   : 375
	            });               
	            
	            return false;
	       }
        }
        
        
        return true;
    };
    
	var onFormInitializeFailure = function(form, response, options)
	{
        de.intrabuild.groupware.ResponseInspector.handleFailure(response, {
            onLogin: {
                fn : function(){
                    form.receiveTypeStore.load();
                },
                scope : form
            }
        });		
	};
	
    var onFormInitialize = function(form)
    {
		for (var i = 0, max_i = queue.length; i < max_i; i++) {
			Ext.Ajax.request(queue[i]);
        }
        
        queue = [];
    };
    
	var onRecipientsLoadException = function(response, options)
	{
        de.intrabuild.groupware.ResponseInspector.handleFailure(response, {
            onLogin: {
                fn : function(){
                    Ext.Ajax.request(options);
                },
                scope : de.intrabuild.groupware.email.EmailEditorManager
            }
        }); 		
	};
	
    var onRecipientsLoad = function(response, options)
    {
        if (!formValues[options.panelId]) {
            return;
        }
		        
		var data = de.intrabuild.groupware.ResponseInspector.isSuccess(response);
		
		if (data === null) {
			return onRecipientsLoadException(response, options);
		}		
        				
        var recRecs = [];
		var rdata = data.draft.recipients;
		var rRec = de.intrabuild.groupware.email.RecipientRecord;
		var tst  = null;
			
		if (rdata.to) {
			tst = rdata.to;
			for (var i = 0, max_i = tst.length; i < max_i; i++) {
	            tmpRecord = new rRec({receive_type : 'to', address : tst[i]}); 
	            recRecs.push(tmpRecord);
        	}
		}	
		
		if (rdata.cc) {
			tst = rdata.cc;
			for (var i = 0, max_i = tst.length; i < max_i; i++) {
	            tmpRecord = new rRec({receive_type : 'cc', address : tst[i]}); 
	            recRecs.push(tmpRecord);
        	}
		}	
			
		if (rdata.bcc) {
			tst = rdata.bcc;
			for (var i = 0, max_i = tst.length; i < max_i; i++) {
	            tmpRecord = new rRec({receive_type : 'bcc', address : tst[i]}); 
	            recRecs.push(tmpRecord);
        	}
		}	
        
		var draft = data.draft;
		
		var cs      = draft.classSubstitute;
		var message = draft.message;

		if (cs && message) {
            message = message.replace(
                new RegExp(cs, 'g'), 
				'de-intrabuild-groupware-email-EmailForm-htmlEditor-blockquote'
            );
		}
		
        Ext.apply(formValues[options.panelId], {
            id         : draft.id,
            disabled   : false,
            subject    : draft.subject,
            message    : message,
            accountId  : draft.groupwareEmailAccountsId,
            recipients : recRecs,
            folderId   : draft.groupwareEmailFoldersId
        });
        
        completeForm(options.panelId);
        
        Ext.getCmp(options.panelId).setTitle(getTitle(draft.subject));
    };
    
    var completeForm = function(panelId)
    {
        if (formValues[panelId].disabled == true) {
            showLoadMask('loading');
            controlBar.setDisabled(true);
            return;
        } else {
            controlBar.setDisabled(false);
            form.loadMask.hide();
        }

        if (!activePanel || activePanel.id != panelId) {
            return;
        }        
        
        subjectField.setValue(formValues[panelId].subject);
        htmlEditor.setValue(formValues[panelId].message);
        
        //htmlEditor.tb.items.get('sourceedit').pressed = formValues[panelId].sourceEditMode;
        //htmlEditor.toggleSourceEdit(formValues[panelId].sourceEditMode);
        //htmlEditor.toggleSourceEdit(formValues[panelId].sourceEditMode);
        //htmlEditor.tb.items.get('sourceedit').toggle(formValues[panelId].sourceEditMode);
        
        if (formValues[panelId].accountId) {
            accountField.setValue(formValues[panelId].accountId);
			_attachSignature(panelId, formValues[panelId].accountId);
        }
		
        
        recipientStore.removeAll();
        recipientStore.add(formValues[panelId].recipients);        
        
    };
    
    var init = function()
    {
        if (!contentPanel) {
            contentPanel = de.intrabuild.util.Registry.get('de.intrabuild.groupware.ContentPanel');
        }
    };    
    
    var registerToolbar = function(panel)
    {
        if (controlBar == null) {
            var tbarManager = de.intrabuild.groupware.ToolbarManager;      
            
            controlBar = new Ext.Toolbar([{   
                cls     : 'x-btn-text-icon',
                iconCls : 'de-intrabuild-groupware-email-EmailForm-toolbar-buttonSend-icon',
                text    : '&#160;'+de.intrabuild.Gettext.gettext("Send now"),
                handler : onSend
              },{   
                cls  	: 'x-btn-text-icon',
                iconCls : 'de-intrabuild-groupware-email-EmailForm-toolbar-buttonOutbox-icon',
                text	: '&#160;'+de.intrabuild.Gettext.gettext("Move to outbox"),
                handler : onOutbox
              } ,'-', {
                cls     : 'x-btn-text-icon',
                iconCls : 'de-intrabuild-groupware-email-EmailForm-toolbar-buttonDraft-icon',
                text    : '&#160;'+de.intrabuild.Gettext.gettext("Save as draft"),
                handler : onSaveDraft
            }]);        

            tbarManager.register('de.intrabuild.groupware.email.EmailForm.toolbar', controlBar);
        }
    };    
    
    var cacheFormValues = function(panelId)
    {
        formValues[panelId].subject = subjectField.getValue();
        if (!htmlEditor.sourceEditMode) {
            htmlEditor.pushValue();
        }
        formValues[panelId].sourceEditMode = htmlEditor.sourceEditMode;
		formValues[panelId].message = htmlEditor.getValue();
        formValues[panelId].accountId  = accountField.getValue();
        formValues[panelId].recipients = [];
        for (var i = 0, max_i = recipientStore.getCount(); i < max_i; i++) {
            formValues[panelId].recipients.push(recipientStore.getAt(i).copy());
        }  
    };
    
// -----------------------------Toolbar listeners----------------------------------   

    var onOutbox = function()
    {
        var recipients = [];
        
        var validRecipients = false;
        
        recipientsGrid.stopEditing();
        
        for (var i = 0, max_i = recipientStore.getCount(); i < max_i; i++) {
            recipients.push(recipientStore.getAt(i).copy());
            if (recipients[i].data.address.trim() != "") {
                validRecipients = true;
            }
        }    
        
        // check if any valid email-addresses have been submitted
        if (!validRecipients) {
            var msg  = Ext.MessageBox;
            
            msg.show({
                title   : de.intrabuild.Gettext.gettext("Error - specify recipient(s)"),
                msg     : de.intrabuild.Gettext.gettext("Please specify one or more recipients for this message."),
                buttons : msg.OK,
                icon    : msg.WARNING,
                scope   : this,
                cls     :'de-intrabuild-msgbox-warning',
                width   : 400
            });      
            
            return;         
        }
        
        var panelId = activePanel.id;
        formValues[panelId].disabled = true;
        formValues[panelId].pending  = true;
        
        // will throw an error in ext2.0, so catch it
        try {
            activePanel.setIconClass('de-intrabuild-groupware-pending-icon');
        } catch (e) {
            // ignore
        }
        
        showLoadMask('outbox');
        controlBar.setDisabled(true);
        
        var url = '/groupware/email/move.to.outbox/format/json';
        
        var params = {
            panelId    : panelId,
            folderId   : formValues[panelId].folderId,
            subject    : subjectField.getValue(),
            message    : htmlEditor.getValue(),
            accountId  : accountField.getValue(),
            recipients : recipients
        };
        
        cacheFormValues(panelId);
        
        Ext.Ajax.request({
            url            : url,
            params         : params,
            success        : onOutboxSuccess, 
            failure        : onOutboxFailure,
            disableCaching : true
        }); 
    };


    var onSend = function()
    {
        var recipients = [];
        
        var validRecipients = false;
        
        recipientsGrid.stopEditing();
        
        for (var i = 0, max_i = recipientStore.getCount(); i < max_i; i++) {
            recipients.push(recipientStore.getAt(i).copy());
            if (recipients[i].data.address.trim() != "") {
                validRecipients = true;
            }
        }    
        
        // check if any valid email-addresses have been submitted
        if (!validRecipients) {
            var msg  = Ext.MessageBox;
            
            msg.show({
                title   : de.intrabuild.Gettext.gettext("Error - specify recipient(s)"),
                msg     : de.intrabuild.Gettext.gettext("Please specify one or more recipients for this message."),
                buttons : msg.OK,
                icon    : msg.WARNING,
                scope   : this,
                cls     :'de-intrabuild-msgbox-warning',
                width   : 400
            });      
            
            return;         
        }
        
        var panelId = activePanel.id;
        formValues[panelId].disabled = true;
        formValues[panelId].pending  = true;
        
        // will throw an error in ext2.0, so catch it
        try {
            activePanel.setIconClass('de-intrabuild-groupware-pending-icon');
        } catch (e) {
            // ignore
        }
        
        showLoadMask('sending');
        controlBar.setDisabled(true);
        
        var url = '/groupware/email/send/format/json';
        
        var params = {
            panelId    : panelId,
            folderId   : formValues[panelId].folderId,
            subject    : subjectField.getValue(),
            message    : htmlEditor.getValue(),
            accountId  : accountField.getValue(),
            recipients : recipients
        };
        
        cacheFormValues(panelId);
        
        Ext.Ajax.request({
            url            : url,
            params         : params,
            success        : onSendSuccess, 
            failure        : onSendFailure,
            disableCaching : true
        }); 
    };

    /**
     * Sets the current panel as deactive and inits a XHttpRequest. As soon as the
     * request finishs, the callback will reset the panel to active.
     *
     */
    var onSaveDraft = function()
    {
        var panelId = activePanel.id;
        formValues[panelId].disabled = true;
        formValues[panelId].pending  = true;
        
        // will throw an error in ext2.0, so catch it
       /* try {
            activePanel.setTitle(activePanel.getTitle(), 'de-intrabuild-groupware-pending-icon');
        } catch (e) {
            // ignore
        }*/
        
        showLoadMask('saving');
        controlBar.setDisabled(true);
        
        recipientsGrid.stopEditing();
        
        var url = '/groupware/email/save.draft/format/json';
        
        var recipients =[];
        var rec = null;
        for (var i = 0, max_i = recipientStore.getCount(); i < max_i; i++) {
        	rec = recipientStore.getAt(i).copy();
            recipients.push([rec.data.receive_type, rec.data.address]);
        }        
        
        var params = {
        	id		   : formValues[panelId].id,
            panelId    : panelId,
            folderId   : formValues[panelId].folderId,
            subject    : subjectField.getValue(),
            message    : htmlEditor.getValue(),
            accountId  : accountField.getValue(),
            recipients : recipients
        };
        
        cacheFormValues(panelId);
        
        Ext.Ajax.request({
            url            : url,
            params         : params,
            success        : onSaveDraftSuccess, 
            failure        : onSaveDraftFailure,
            disableCaching : true
        }); 
    };
    
    var clearPendingState = function(id)
    {
        var panelId = activePanel ? activePanel.id : null;
        
        try {
            Ext.getCmp(id).setIconClass('de-intrabuild-groupware-email-EmailForm-icon');
        } catch (e) {
            // @bug ext2.0
            // ignore, buggy in ext 2.0
        }
        
        formValues[id].disabled = false;
        formValues[id].pending  = false;
        
        completeForm(id);
    };

    var onSaveDraftSuccess = function(response, parameters)
    {
    	var data  = parameters.params;
    	var messageId = data.id;
        var panelId = data.panelId;
        clearPendingState(panelId);
        
        var json = de.intrabuild.util.Json;

        if (!json.isResponseType('integer', response.responseText)) {
            onSaveDraftFailure(response, parameters, true);
            return;
        }            
        
        var savedId = json.getResponseValue(response.responseText);
        
        Ext.apply(data, {
        	from : accountField.store.getById(data.accountId).data.address
        });
        
        formValues[panelId].dirty = false;
        form.fireEvent('savedraft', data, savedId);
    };
    
    var onSaveDraftFailure = function(response, parameters, called)
    {
        if (called !== true) {
            clearPendingState(parameters.params.panelId);
        }
        
		de.intrabuild.groupware.ResponseInspector.handleFailure(response, {
            title : de.intrabuild.Gettext.gettext("Error - Could not save draft")
		});
    };
    

    var onSendSuccess = function(response, parameters)
    {
    	var data = parameters.params;
        var panelId = data.panelId;
        clearPendingState(panelId);
        
        var json = de.intrabuild.util.Json;

        if (!json.isResponseType('integer', response.responseText)) {
            onSendFailure(response, parameters, true);
            return;
        }            
		var savedId = json.getResponseValue(response.responseText);
		
		Ext.apply(data, {
        	from : accountField.store.getById(data.accountId).data.address
        });
		
		form.fireEvent('sendemail', data, savedId);
		 
        contentPanel.un('beforeremove',  onBeforeClose, de.intrabuild.groupware.email.EmailEditorManager);        
        contentPanel.remove(Ext.getCmp(panelId));
        contentPanel.on('beforeremove',  onBeforeClose, de.intrabuild.groupware.email.EmailEditorManager);        
        
        
        
    };
    
    var onSendFailure = function(response, parameters, called)
    {
        if (called !== true) {
            clearPendingState(parameters.params.panelId);
        }
        
        de.intrabuild.groupware.ResponseInspector.handleFailure(response, {
            title : de.intrabuild.Gettext.gettext("Error - Could not send message")
        });      
        
    };    
    
    var onOutboxSuccess = function(response, parameters)
    {
    	var data = parameters.params;
        var panelId = data.panelId;
        clearPendingState(panelId);
        
        var json = de.intrabuild.util.Json;

        if (!json.isResponseType('integer', response.responseText)) {
            onSendFailure(response, parameters, true);
            return;
        }            
		var savedId = json.getResponseValue(response.responseText);
		
		Ext.apply(data, {
        	from : accountField.store.getById(data.accountId).data.address
        });
		
		form.fireEvent('movedtooutbox', data, savedId);
		 
        contentPanel.un('beforeremove',  onBeforeClose, de.intrabuild.groupware.email.EmailEditorManager);        
        contentPanel.remove(Ext.getCmp(panelId));
        contentPanel.on('beforeremove',  onBeforeClose, de.intrabuild.groupware.email.EmailEditorManager);        
    };
    
    var onOutboxFailure = function(response, parameters, called)
    {
        if (called !== true) {
            clearPendingState(parameters.params.panelId);
        }
        
        de.intrabuild.groupware.ResponseInspector.handleFailure(response, {
            title : de.intrabuild.Gettext.gettext("Error - Could not move message to outbox")
        });    
        
    };        
    
// -----------------------------Form listeners----------------------------------

	/**
	 * Callback whenever the return or enter key was pressed in the html editor.
	 * This will search for the first blockquote and split it in half to 
	 * make quoting possible.
	 *
	 */ 
	var onHtmlEditorEdit = function(string, eventObject)
	{
		var doc = htmlEditor.doc;
		
		var splitRange = Ext.isIE 
		                 ? doc.selection.createRange() 
						 : htmlEditor.win.getSelection().getRangeAt(0);
	    
	    var id = '_'+(new Date()).getTime();
	    
	    if (Ext.isIE) {
	    	splitRange.pasteHTML('<span id="'+id+'"></span>');
		} else {
			var span = doc.createElement('span');
			span.id = id;
            splitRange.surroundContents(span);	
	    }
	    
	    var splitter = doc.getElementById(id);
		splitter.id = "";
	    var parent = splitter.parentNode;
	    var quoteEl = null;
	    var tagName = "";
	    while (parent) {
	    	tagName = parent.tagName.toLowerCase();
	    	if (tagName == 'blockquote') {
	    		quoteEl = parent;
	    	} else if (tagName == 'body') {
	    		break;	
	    	}
	    	parent = parent.parentNode;
	    }
		
	    
	    if (quoteEl) {
	    	eventObject.stopEvent();
	    	var splitterClone = splitter.cloneNode(false);
	    	var dividedNode   = utilDom.divideNode(splitter , splitterClone, quoteEl);
			
			if(!quoteEl.nextSibling){
				quoteEl.parentNode.appendChild(dividedNode);
			} else {
				quoteEl.parentNode.insertBefore(dividedNode, quoteEl.nextSibling);
			}
			
			var br  = doc.createElement('br');
			var div = doc.createElement('div');
			div.className = 'de-intrabuild-groupware-email-EmailForm-htmlEditor-insertDiv';
			quoteEl.parentNode.insertBefore(div, dividedNode);
			div.innerHTML="&nbsp;";
			
			if (Ext.isIE && splitRange) {
				splitRange.move("character",2);
				splitRange.select();
				div.innerHTML="";
			} else {
				htmlEditor.win.getSelection().collapse(div, 0);
			}
			
			splitterClone.parentNode.removeChild(splitterClone);
			splitter.parentNode.removeChild(splitter);
			
			var fc = dividedNode.firstChild;
			if (fc && fc.tagName && fc.tagName.toLowerCase() == 'br') {
                dividedNode.removeChild(fc);
            }
			
	    } else {
	    	splitter.parentNode.removeChild(splitter);
	    }
	  
	};


	
	var getTitle = function(value)
	{
		var str = subjectField.getValue().trim();
		
		str = (str == "" && value != undefined) ? value : str;
		
        if (str == "") {
            str = de.intrabuild.Gettext.gettext("(no subject)");
        } else {
            str = Ext.util.Format.htmlEncode(str);    
        }  	
        
        return str;
	};
	
	var onSubjectValueChange = function()
    {
    	formValues[activePanel.id].dirty = true;
        activePanel.setTitle(getTitle());
    }

    /**
     * grid - This grid
     * record - The record being edited
     * field - The field name being edited
     * value - The value being set
     * originalValue - The original value for the field, before the edit.
     * row - The grid row index
     * column - The grid column index
     */    
    var onAfterEdit = function(editObject)
    {
        var row   = editObject.row, 
            value = editObject.value, 
            grid  = editObject.grid,
            store = grid.store;
            
        store.commitChanges();
        
        formValues[activePanel.id].dirty = true;
        
		if (value.trim() != "" && store.getAt(row).data.address.trim() != "" && 
            store.getAt(row+1) == null) {
            
            var tmpRecord = new de.intrabuild.groupware.email.RecipientRecord({
                receive_type : 'to',
                address : ''
            });            
            
            store.insert(row+1, tmpRecord);
            grid.view.ensureVisible(row+1, 0);
        }
    };
    
	/**
	 * Listener for the select event of the "from"-combobox of the EmailForm.
	 * 
	 * @param {Object} comboBox
	 * @param {Object} record
	 * @param {Number} index
	 */
    var onAccountSelect = function(comboBox, record, index)
    {
		formValues[activePanel.id].dirty = true;
		_attachSignature(activePanel.id, record.id, true);
		
    };
	
	/**
	 * 
	 * @param {Object} panelId
	 * @param {Object} accountId
	 * @param {Boolean} refresh Wether the signature has to be re-build if the 
	 * signature's container was not found. This should be set to true whenever 
	 * the "select"-event of the account field is involved
	 */
    var _attachSignature = function(panelId, accountId, refresh)
	{
		var store = de.intrabuild.groupware.email.AccountStore.getInstance();
        var rec   = store.getById(accountId);
		
		var sigId = formValues[panelId]['signatureAttached'];
		
		// no signature used yet
        if (sigId === false) {
			if (!rec || !rec.get('isSignatureUsed')) {
	            return;
	        }
			var signature = rec.get('signature')
			formValues[panelId]['signatureAttached'] = '_' + (new Date()).getTime();
			htmlEditor.setValue(
                htmlEditor.getValue() 
				+ _getSignatureHtml(signature, formValues[panelId]['signatureAttached']) 
			);
		} else {
			var doc    = htmlEditor.doc;
			var sigDiv = doc.getElementById(sigId);
			
			// the selected account does not have a signature configured,
			// remove any currently used signature
			if (!rec || !rec.get('isSignatureUsed')) {
                if (!sigDiv) {
					return;
				} else {
					sigDiv.parentNode.removeChild(sigDiv);
					formValues[panelId]['signatureAttached'] = false;
					htmlEditor.syncValue();
					return;
				}   
            }
            
            if (!sigDiv && refresh === true) {
				var sig = _getSignatureHtml(
                    rec.get('signature'),
                    formValues[panelId]['signatureAttached']
                );
                htmlEditor.setValue(htmlEditor.getValue()+sig); 
            } else if (sigDiv) {
				sigDiv.innerHTML = _prepareSignature(rec.get('signature'));
				htmlEditor.syncValue();
			} 
		}		
	};	
    
	var _prepareSignature = function(signature)
	{
		signature = signature.replace(/\r\n/g, "<br />");
        signature = signature.replace(/\r/g, "<br />");
        signature = signature.replace(/\n/g, "<br />");
		
		return '-- <br />' + signature;
	};
	
	var _getSignatureHtml = function(signature, id)
	{
	    
	    return '<br /><br /><div ' +
			   'class="de-intrabuild-groupware-email-EmailForm-htmlEditor-signature" ' +
			   'id="' +
			    id +
			   '">' +
			   _prepareSignature(signature);
	           '</div>';
	};
	
    var onMessageEdit = function()
    {
    	formValues[activePanel.id].dirty = true;    	
    };
   
// ----------------------------Panel listeners----------------------------------    
    var onDeactivatePanel = function(panel)
    {
    	//htmlEditor.un('push', onMessageEdit, de.intrabuild.groupware.email.EmailEditorManager);
    	htmlEditor.un('sync', onMessageEdit, de.intrabuild.groupware.email.EmailEditorManager);
    	
        recipientsGrid.stopEditing();
        if (!formValues[panel.id].disabled) {
            cacheFormValues(panel.id);
        }
     
        htmlEditor.setValue('');
        subjectField.setValue('');
        recipientStore.removeAll();
        
        //htmlEditor.tb.items.get('sourceedit').pressed = false;
        //htmlEditor.toggleSourceEdit(false);
        //htmlEditor.toggleSourceEdit(false);
        //htmlEditor.tb.items.get('sourceedit').toggle(false);
        
        activePanel = null;
        var tbarManager = de.intrabuild.groupware.ToolbarManager;
        tbarManager.hide('de.intrabuild.groupware.email.EmailForm.toolbar');
    }
    
    var onActivatePanel = function(panel)
    {
        if (!panel.rendered) {
            return;
        }
		
		activePanel = panel;
        panel.getActionEl().addClass('x-hide-' + panel.hideMode);
        masterPanel.getActionEl().removeClass('x-hide-' + masterPanel.hideMode);
        
        contentPanel.layout.activeItem = masterPanel;
        contentPanel.layout.layout();
        
        var tbarManager = de.intrabuild.groupware.ToolbarManager;
        tbarManager.show('de.intrabuild.groupware.email.EmailForm.toolbar');
       
        completeForm(panel.id);
        //htmlEditor.on('push', onMessageEdit, de.intrabuild.groupware.email.EmailEditorManager);
    	htmlEditor.on('sync', onMessageEdit, de.intrabuild.groupware.email.EmailEditorManager);
    };
    
    var onDestroyPanel = function(panel)
    {
        formValues[panel.id] = null;
        delete formValues[panel.id];
        
        tabCount--;
        if (tabCount == 0) {
            contentPanel.un('beforeremove',  onBeforeClose, de.intrabuild.groupware.email.EmailEditorManager); 
            contentPanel.remove(masterPanel, true);
            form.destroy();
            form = null;
            masterPanel = null;
            var tbarManager = de.intrabuild.groupware.ToolbarManager;
            tbarManager.destroy('de.intrabuild.groupware.email.EmailForm.toolbar');
            controlBar = null;
            formValues = [];
            subjectField = null;
            recipientsGrid = null;
        }
    };
    
    var showLoadMask = function(type)
    {
        switch (type) {
            case 'loading':
                form.loadMask.msg = messages.loading;
            break;
            case 'saving':
                form.loadMask.msg = messages.saving;
            break;
            case 'sending':
                form.loadMask.msg = messages.sending;
            break;
            case 'outbox':
                form.loadMask.msg = messages.outbox;
            break;
        }
        
        form.loadMask.show();
    };
    
    return {
        
        /**
         * Creates a new panel to create/edit an email.
         *
         * @param {Number} id The id of the email to edit. If not supplied,
         * a raw form will be loaded to create a new email. If supplied, 
         * corresponding data will be loaded from teh server to fill out the 
         * form and edit it.
         * @param {String} type The type of the action, if an id was supplied. 
         * Possible values are 'edit', 'reply', 'reply_all', 'forward'. 
         *
         */
        createEditor : function(id, type)
        {
            init();
            
            if (id == undefined) {
            	id = -1;	
            }
            
            if (id == -1) {
            	type = 'new';
            }
            
            createPanel(id, type);
        }
        
    };
    
    
}();

de.intrabuild.groupware.email.EmailForm = function(config){
    
    Ext.apply(this, config);
   
    this.addEvents(
        'initialize',
		'initializefailure',
        'savedraft',
        'sendemail',
        'movedtooutbox'
    );
    
    var receiveTypeStore = new Ext.data.JsonStore({
        url    : '/groupware/email/get.receive.types/format/json',
        root   : 'response.value',
        id     : 'id',
        fields : ['id', 'text'],
        autoLoad : false
    });    
    
    receiveTypeStore.on('load',          this.onDefaultStoresLoad, this);
	receiveTypeStore.on('loadexception', this.onDefaultStoresLoadException, this);
    
	this.receiveTypeStore = receiveTypeStore;
    
    var accountStore = de.intrabuild.groupware.email.AccountStore.getInstance();
    
    var view = new Ext.grid.GridView({
        getRowClass : function(record, rowIndex, p, ds){
            return 'de-intrabuild-groupware-email-EmailForm-gridrow';
        }
    });
        
    var findAddressStore = new Ext.data.JsonStore({
        url       : '/groupware/email/get.recipient/format/json',
        root      : 'response.value',
        fields    : ['text'],
        autoLoad  : false,
		listeners : {
			loadexception : {
				fn:  function(proxy, options, response, jsError) {
					de.intrabuild.groupware.ResponseInspector.handleFailure(response);
				}
			}
		}
    });        
    
    var isStandardIndex = accountStore.find('isStandard', true);
    var standardAcc     = accountStore.getAt(isStandardIndex);
    
    
    this.fromComboBox = new Ext.form.ComboBox({
       name : 'from', 
       tpl : '<tpl for="."><div class="x-combo-list-item">{address:htmlEncode} - {name:htmlEncode}</div></tpl>',
       fieldLabel : de.intrabuild.Gettext.gettext("From"),
       anchor     : '100%',
       typeAhead: false,
       triggerAction: 'all',
       editable : false,
       lazyRender:true,
       displayField  : 'address',
       value : (standardAcc ? standardAcc.id : undefined),
       mode : 'local',
       valueField    : 'id',
       listClass: 'x-combo-list-small',
       store : accountStore
    });    
    
    receiveTypeStore.load(); 
    
    var addressQueryComboBox = new Ext.form.ComboBox({
       hideTrigger : true,
       tpl : '<tpl for="."><div class="x-combo-list-item">{text:htmlEncode}</div></tpl>',
       typeAhead: true,
       queryDelay : 250,
       lazyRender:true,
       triggerAction: 'query',
       minChars : 3,
       displayField  : 'text',
       mode : 'remote',
       listClass: 'x-combo-list-small',
       store : findAddressStore
    });
    
    addressQueryComboBox.on('beforequery', function(queryObject){
        var str = queryObject.query;
        if (str.trim() == "") {
            return false;
        }
        var alpha = /^[a-zA-Z_ -.]+$/;
        
        return alpha.test(str);
        
    }, addressQueryComboBox);
    
    this.gridStore = new Ext.data.JsonStore({
       // url      : '/groupware/email/get.email.form.recipients/format/json',
       // root     : 'response.value',
        id       : 'id',
        fields   : ['receive_type', 'address']
       // autoLoad : false
    });    
    
    
    this.grid = new Ext.grid.EditorGridPanel({
        autoExpandColumn : 'address',
        autoExpandMax : 4000,
        autoExpandMin : 0,
        region  : 'center',
        margins : '2 5 2 5',
        style   : 'background:none',
        store   : this.gridStore,
        columns : [{
            id : 'receive_type',
            header: "receive_type", 
            width: 100, 
            dataIndex : 'receive_type',
            editor: new Ext.form.ComboBox({
               typeAhead: false,
               triggerAction: 'all',
               lazyRender:true,
               displayField  : 'text',
               editable: false,
               mode : 'local',
               valueField    : 'id',
               listClass: 'x-combo-list-small',
               store : receiveTypeStore
            }),
            renderer: function(value, p, record) {
                var sRecord = receiveTypeStore.getById(value);
                if(sRecord) {
                    return sRecord.data.text;
                } else {
                    '';
                }
            }
        },{
            id : 'address',
            header: "address",   
            dataIndex: 'address',
            editor: addressQueryComboBox,
            renderer: function(value, p, record) {
                return Ext.util.Format.htmlEncode(value);
            }
        }],
        view : view,
        header : false,
        clicksToEdit:1
    });
    
    this.grid.store.on('update', this.onUpdate, this);
    
    this.grid.on('render', function(){
        this.view.mainWrap.dom.firstChild.style.display = "none";
        this.view.scroller.setStyle('overflow-x', 'hidden');
        this.view.scroller.setStyle('overflow-y', 'scroll');
    }, this.grid);
    

    this.subjectField = new Ext.form.TextField({
        name : 'subject',
        fieldLabel : de.intrabuild.Gettext.gettext("Subject"),
        anchor     : '100%'
    });
    
    this.htmlEditor = new Ext.form.HtmlEditor({
        hideMode    : 'offsets',
        hideLabel   : true,
        name        : 'msg',
        anchor      : '100% -0',
        enableLinks : false,
        defaultFont : 'courier new',
        enableSourceEdit : false,
        defaultAutoCreate : {
            tag: "textarea",
            style:"width:500px;height:300px;font-family:Courier New;font-size:14px;",
            autocomplete: "off"
        }
    });
    
    this.htmlEditor.getDocMarkup = function(){
        
        if (!this.__doc_markup__) {
            var excludeMask = {
                href : '*/ext-all.css' 
            };
            
            var getCssTextFromStyleSheet = de.intrabuild.util.Dom.getCssTextFromStyleSheet;
                
            var body = getCssTextFromStyleSheet(
                '.de-intrabuild-groupware-email-EmailForm-htmlEditor-body',
                excludeMask
            );
			
			var insertDiv = getCssTextFromStyleSheet(
                '.de-intrabuild-groupware-email-EmailForm-htmlEditor-insertDiv',
                excludeMask
            );
            
			var cblockquote = getCssTextFromStyleSheet(
                 '.de-intrabuild-groupware-email-EmailForm-htmlEditor-body blockquote',
                excludeMask
            );
			
			var signature = getCssTextFromStyleSheet(
                '.de-intrabuild-groupware-email-EmailForm-htmlEditor-signature',
				excludeMask
			);
			
            var blockquote = getCssTextFromStyleSheet(
                 '.de-intrabuild-groupware-email-EmailForm-htmlEditor-blockquote',
                excludeMask
            );

            var abs = [];
            for (var i = 0; i < 10; i++) {
				abs.push('blockquote');
				blockquote += getCssTextFromStyleSheet(
	                 '.de-intrabuild-groupware-email-EmailForm-htmlEditor-blockquote '+abs.join(' '),
	                excludeMask
	            );	
			}
            						
			this.__doc_markup__ =  '<html>'
								  + '<head>'
								  + '<META http-equiv="Content-Type" content="text/html; charset=UTF-8">'
                                  + '<title></title>'
								  + '<style type="text/css">'
                                  + body
								  + ' '
                                  + cblockquote
                                  + ' '
                                  + blockquote
								  + ' '
								  + insertDiv
								  + ' '
                                  + signature
                                  + '</style></head>'
                                  + '<body class="de-intrabuild-groupware-email-EmailForm-htmlEditor-body">'
                                  + '</body></html>';
        }
        
        return this.__doc_markup__;
    };
    
    de.intrabuild.groupware.email.EmailForm.superclass.constructor.call(this, {
        items : [{
            layout : 'border',
            bodyStyle : 'background-color:#F6F6F6',
            region : 'north',
            split : true,
            hideMode : 'offsets',
            height:125,
            minSize:125,
            items  : [
                new Ext.form.FormPanel({
                    labelWidth  : 30,
                    region : 'north',
                    height : 20,
                    minSize: 20,
                    hideMode : 'offsets',
                    margins: '4 5 2 5',
                    style  : 'background:none',
                    baseCls     : 'x-small-editor',
                    border : false,
                    defaults : {
                        labelStyle : 'width:30px;font-size:11px'
                    },
                    defaultType : 'textfield',
                    items : [
                        this.fromComboBox
                    ]
              }), this.grid, 
                new Ext.form.FormPanel({
                    labelWidth  : 45,
                    region : 'south',
                    height : 20,
                    hideMode : 'offsets',
                    minSize: 20,
                    style  : 'background:none',
                    margins: '2 5 4 5',
                    baseCls     : 'x-small-editor',
                    border : false,
                    defaults : {
                        labelStyle : 'width:45px;font-size:11px'
                    },
                    defaultType : 'textfield',
                    items : [
                        this.subjectField
                    ]
              })
          ]},
            new Ext.form.FormPanel({
                region : 'center',
                hideMode : 'offsets',
                baseCls     : 'x-small-editor',
                border:false,
                items  : [this.htmlEditor]
            })
        ]
        
    });

    this.loadMask = null;
    
    de.intrabuild.util.Registry.register('de.intrabuild.groupware.email.EmailForm', this, true);
};


Ext.extend(de.intrabuild.groupware.email.EmailForm, Ext.Panel, {

	__is : 'de.intrabuild.groupware.email.EmailForm',

    initialized  : false,
    
    
    onDefaultStoresLoad : function(store, records, options)
    {
        this.initialized = true;
        this.fireEvent('initialize', this);
    },
	
    onDefaultStoresLoadException : function(proxy, options, response, jsError)
    {
		this.fireEvent('initializefailure', this, response, options);
    },	
    
    onUpdate : function(store, record, operation)
    {
        
    }
});


de.intrabuild.groupware.email.RecipientRecord = Ext.data.Record.create([
    {name: 'receive_type'}, {name: 'address'}
]);
            