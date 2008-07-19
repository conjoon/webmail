/**
 * intrabuild
 * (c) 2002-2008 siteartwork.de/MindPatterns
 * license@siteartwork.de
 *
 * $Author: T. Suckow $
 * $Id: _scripts.phtml 2 2008-06-21 10:38:49Z T. Suckow $
 * $Date: 2008-06-21 12:38:49 +0200 (Sa, 21 Jun 2008) $ 
 * $Revision: 2 $
 * $LastChangedDate: 2008-06-21 12:38:49 +0200 (Sa, 21 Jun 2008) $
 * $LastChangedBy: T. Suckow $
 * $URL: file:///F:/svn_repository/intrabuild/trunk/src/www/application/modules/default/views/scripts/index/_scripts.phtml $ 
 */

Ext.namespace('de.intrabuild.groupware');  
  
/**
 * @class de.intrabuild.groupware.ReponseInspector
 * 
 * A singleton for inpecting raw responses as returned by the server and taking
 * appropriate action. 
 * Every cllback that is either defined for "success" or "failure", or any 
 * "loadexception" should use the inspector as it defines some basic handling 
 * of application specific responses, such as error messages.
 * Some errors returned by the applicaton (e.g. the server) are recoverable, such 
 * as an authentication failure. In this case, the server returns the 401 http status
 * code to which the {@see de.intrabuild.groupware.Reception} listens, and 
 * automatically builds a login window. Since the 401 is treated as a response error,
 * the inspector's handleFailure should be called along with a callback for the 
 * 'loginsuccessfull' event of the reception.
 * The signature of a json encoded error-response for an authentication failure 
 * is as follows:
 * <lu>
 *  <li>success : false</li>
 *  <li>authorized: false</li>
 *  <li>error : [an erro object given a detailed description of the error that occured</li>
 *  </lu>
 * In the same way is the "locked" state of the application treated, i.e. if the 
 * application's current state was locked until the user provides his authentication
 * credentials again. The session will be refreshed automatically but no other
 * requests are allowed except for this session-keep alive. Any other request will  
 * also be responded by the server with a 401 status code and the following response
 * signature:
 * <lu>
 *  <li>success : false</li>
 *  <li>locked: true</li>
 *  <li>error: [an erro object given a detailed description of the error that occured</li>
 *  </lu>
 * When the raw responses are passed to the inspector, the appropriate action 
 * will be taken.
 *  
 * @singleton
 */  
de.intrabuild.groupware.ResponseInspector = function() {
	
    /**
     * Tries to json-decode the passed parameter and returns the result. 
     * If the parameter is already of the type Object, no action will be taken
     * and the parameter will be returned unprocessed. 
     * If the parameter is of type String and cannot be json-decoded, an error
     * will be thrown. An error will be thrown if the value is neither of type 
     * String nor of type Object.  
     * 
     * @param {Object} value
     * 
     * @return Object
     * 
     * @see Ext.decode
     */
    var _tryDecode = function(value)
	{
		if ((typeof value).toLowerCase() === 'string') {
			var v = null;
			try {
				v = Ext.decode(value);
			} catch (e) {
				throw(
				    'de.intrabuild.groupware.ResponseInspector._tryDecode: '
				    + 'Argument does not seem to be json-encoded.'
                );
			}
			return v;
		} else if ((typeof value).toLowerCase() === 'object') {
			return value;
		}
		
		throw(
            'de.intrabuild.groupware.ResponseInspector._tryDecode: '
            + 'Argument is neither of type String nor Object.'
        );
	};

    return {
		
		/**
		 * Failure type indicating response said "not authorized" 
		 * @param {Number}
		 */
		FAILURE_AUTH : 4,
		
        /**
         * Failure type indicating response said "workbench locked" 
         * @param {Number}
         */		
		FAILURE_LOCK : 2,
		
        /**
         * Type indicating response stated no specific failure 
         * @param {Number}
         */     
		FAILURE_NONE : -1,
		
		/**
		 * Returns true if the passed argument contains a property called 
		 * "authorized" which equals to false.
		 * The passed parameter can be either of type String or Object. If the
		 * type is String, the method will try to json-decode the string.
		 * Throws an error if that fails.   
		 * This method must only be called when inspecting ajax-responses.
		 *  
		 * @param {Object|String} response The response to inspect for an 
		 * authentication failure.
		 * 
		 * @return {Boolean} true, if the decoded argument has a property
		 * "authorized" which equals to false, otherwise true 
		 * 
		 * @see _tryDecode
		 * 
		 * @throws Error if decoding the argument fails
		 */
		isAuthenticationFailure : function(response)
		{
			var obj = _tryDecode(response);
						
			if (obj.authorized === false) {
				return true;
			}
			
			return false;
		},
		
        /**
         * Returns true if the passed argument contains a property called 
         * "locked" which equals to true.
         * The passed parameter can be either of type String or Object. If the
         * type is String, the method will try to json-decode the string.
         * Throws an error if that fails.   
         * This method must only be called when inspecting ajax-responses.
         *  
         * @param {Object|String} response The response to inspect, expecting 
         * that it indicates the workbench lock-state. 
         * 
         * @return {Boolean} true, if the decoded argument has a property
         * "locked" which equals to true, otherwise false 
         * 
         * @see _tryDecode
         * 
         * @throws Error if decoding the argument fails
         */		
        isWorkbenchLocked : function(response)
        {
            var obj = _tryDecode(response);
                        
            if (obj.locked === true) {
                return true;
            }
            
            return false;
        },		
		
		/**
		 * Returns the failure type associated with the response values, if any.
		 * 
         * @param {String|XmlHttpResponse} response The response to inspect for errors
         * 
		 * @return {Number}
		 */
		getFailureType : function(response)
		{
            var resp = null;
            
            if (response) {
                if (response.responseText) {
                    resp = _tryDecode(response.responseText);
                } else {
                    resp = _tryDecode(response);
                }
            }			
			
			if (resp.authorized === false) {
				return this.FAILURE_AUTH;
			}
			
			if (resp.locked === true) {
                return this.FAILURE_LOCK;
            }
			
			return this.FAILURE_NONE;
		},
		
		/**
		 * Returns the json decoded response if there was a property called
		 * "success" which was set to "true", otherwise null.
		 * 
		 * @param {String|XmlHttpResponse}
		 * 
		 * @return {Object}
		 * 
		 */
		isSuccessfull : function(response)
		{
			var resp = null;
			
		  	if (response.responseText) {
                resp = _tryDecode(response.responseText);
            } else {
                resp = _tryDecode(response);
            }
			
			if (resp.success === true) {
				return resp; 
			}
			
			return null;
		},
		
        /**
         * A method for taking automatic actions when a erroneous response from
         * the server is expected.
         * Shows a message dialog given more detailed information about the error.
         * If the response object did contain an error, it's error-details will
         * be shown. In any other case, an "unexpected error" dialog will be 
         * rendered, showing the raw responseText-property of the response.
         * Depending on the configuration of the parameter "options", callbacks 
         * may be attached to the {@see de.intrabuild.groupware.Reception._loginWindow},
         * if the error is recoverable. A recoverable error is for example an
         * authentication failure, when the user has to re-authenticate at the 
         * application.
         * Also, properties in the options-object, which could be properties of 
         * the error-dialog will be given presedence to the properties that get
         * set over this method.
         * Valid proerties of the options-object are:
         * <ul>
         *  <li>onLogin : an observable-valid callback for the 'loginsuccessfull'
         *  event of the {@see de.intrabuild.groupware.Reception}, containing 
         *  the properties 
         *   <ul>
         *    <li>fn : the function to call</li>
         *    <li>scope : the scope in which the function gets called</li>    
         *   </ul>
         *  </li>
         *  <li>title : the title displayed in the error-dialogs titlebar</li>
         * </ul> 
         *  
         * @param {String|XmlHttpResponse} response The response to inspect for errors
         * 
         * 
         * @see _tryDecode
         * 
         * @throws Error if decoding the argument fails
         * 
         * @see de.intrabuild.util.Json.forceErrorDecode
         */     		
		handleFailure : function(response, options)
		{
			var resp = null;
			
			if (response) {
				if (response.responseText) {
					resp = _tryDecode(response.responseText);
				} else {
					resp = _tryDecode(response);
				}
			}
			
			// check if the response send an authentication failure
			if (options && options.onLogin) {
				if (de.intrabuild.groupware.ResponseInspector.isAuthenticationFailure(resp)) {
					var ol = options.onLogin;
					de.intrabuild.groupware.Reception.onLogin(
                        ol.fn, ol.scope
					);	
				}
			}
			
            var json = de.intrabuild.util.Json;
            var msg  = Ext.MessageBox;

            var error = json.forceErrorDecode(response);
              
            msg.show({
                title   : error.title || 'Error',
                msg     : error.message,
                buttons : msg.OK,
                icon    : msg[error.level.toUpperCase()],
                cls     :'de-intrabuild-msgbox-'+error.level,
                width   : 400
            });			
		}
		
	};
	
	
}();