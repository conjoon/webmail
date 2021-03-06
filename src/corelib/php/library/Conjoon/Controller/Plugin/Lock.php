<?php
/**
 * conjoon
 * (c) 2007-2015 conjoon.org
 * licensing@conjoon.org
 *
 * conjoon
 * Copyright (C) 2014 Thorsten Suckow-Homberg/conjoon.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
 * Zend_Controller_Plugin_Abstract
 */
require_once 'Zend/Controller/Plugin/Abstract.php';

 /**
  * A plugin that checks if the session of a user is currently locked.
  *
  * If the session is lcoked, the current request is being denied and instead
  * the index-action of the reception controller is being processed.
  *
  * @uses Zend_Controller_Plugin_Abstract
  * @package Conjoon_Controller
  * @subpackage Plugin
  * @category Plugins
  *
  * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
  */
class Conjoon_Controller_Plugin_Lock extends Zend_Controller_Plugin_Abstract {


    /**
     * Called before the dispatch loop gets processed.
     *
     * This callback allows for proxy or filter behavior.  By altering the
     * request and resetting its dispatched flag (via
     * {@link Zend_Controller_Request_Abstract::setDispatched() setDispatched(false)}),
     * the current action may be skipped.
     *
     * The method checks for an authenticated user.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
        require_once 'Zend/Session/Namespace.php';
        require_once 'Conjoon/Keys.php';

        $receptionControllerNs = new Zend_Session_Namespace(
            Conjoon_Keys::SESSION_CONTROLLER_RECEPTION
        );

        $isLocked = $receptionControllerNs->locked;

        if ($isLocked === true) {
            // the following requests may still be processed when a user's
            // session is locked
            if ($request->controller == 'registry' && $request->module == 'default') {
                switch ($request->action) {
                    // we need the registry, at least the basic entries
                    case 'get.entries':
                    return;
                }
            } else if ($request->controller == 'reception' && $request->module == 'default') {
                switch ($request->action) {
                    // the user wants to unlock the session. Give him a try!
                    case 'unlock':
                    // another request for locking the session may still be made!
                    case 'lock':
                    // someone requests a fresh relogin, so let him logout the
                    // locked session first!
                    case 'logout':
                    // the frontend needs to know who's the user which session
                    // got locked!
                    case 'get.user':
                    // the frontend needs to keep the user's session alive!
                    case 'ping':
                    // auth token failure? let him pass
                    case 'auth.token.failure':
                    return;
                }
            }

            // deny access and route to index action of reception
            $request->setModuleName('default')
                    ->setControllerName('reception')
                    ->setActionName('index')
                    ->setDispatched(false);
        }
   }
}