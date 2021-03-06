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
 * Zend_Controller_Action
 */
require_once 'Zend/Controller/Action.php';


/**
 * Action controller for login/logout.
 *
 * @uses Zend_Controller_Action
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
class ReceptionController extends Zend_Controller_Action {

    const CONTEXT_JSON = 'json';

    const CONTEXT_IPHONE = 'iphone';

    /**
     * Inits this controller and sets the context-switch-directives
     * on the various actions.
     *
     */
    public function init()
    {
        $conjoonContext = $this->_helper->conjoonContext();

        $conjoonContext->addActionContext('logout',   self::CONTEXT_JSON)
                       ->addActionContext('process',  self::CONTEXT_JSON)
                       ->addActionContext('login',    self::CONTEXT_JSON)
                       ->addActionContext('index',    array(
                           self::CONTEXT_JSON,
                           self::CONTEXT_IPHONE,
                       ))
                       ->addActionContext('ping',               self::CONTEXT_JSON)
                       ->addActionContext('lock',               self::CONTEXT_JSON)
                       ->addActionContext('unlock',             self::CONTEXT_JSON)
                       ->addActionContext('auth.token.failure', self::CONTEXT_JSON)
                       ->addActionContext('get.user',           self::CONTEXT_JSON)
                       ->initContext();
    }

    /**
     * Displays the information about the currently logged in user.
     *
     */
    public function getUserAction()
    {
        /**
         * @see Zend_Registry
         */
        require_once 'Zend/Registry.php';

        /**
         * @see Conjoon_Keys
         */
        require_once 'Conjoon/Keys.php';

        $auth = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $user = $auth->getIdentity();

        $user = $user->getDto();

        /**
         * @todo create filter
         */
        unset($user->authToken);

        $this->view->success   = true;
        $this->view->error     = null;
        $this->view->timestamp = time();
        $this->view->user      = $user;
    }

    /**
     * Action for a request that the session for the user sould be locked.
     * Locking the session means that no further requests are being processed
     * (except for the actions ping/logout/login/unlock requests as defined by
     * this controller) until the session is unlocked.
     *
     * @see unlockAction
     */
    public function lockAction()
    {
        require_once 'Zend/Session/Namespace.php';
        require_once 'Conjoon/Keys.php';

        $this->clearInformationForAutoLogin();

        $receptionControllerNs = new Zend_Session_Namespace(
            Conjoon_Keys::SESSION_CONTROLLER_RECEPTION
        );

        $receptionControllerNs->locked = true;

        $this->view->success = true;
        $this->view->error   = null;
        $this->view->locked  = true;
    }

    /**
     * Action for a request that the session for the user sould be unlocked.
     * It is important to check if the submitted user credentials match the
     * user information stored in the auth object, so that no user other account
     * can hijack the current active session!
     */
    public function unlockAction()
    {
        require_once 'Zend/Session/Namespace.php';
        require_once 'Conjoon/Keys.php';
        require_once 'Zend/Registry.php';
        require_once 'Conjoon/BeanContext/Decorator.php';

        $auth = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $id = $auth->getIdentity()->getId();

        /**
         * @todo Filter username and password!
         */
        $username = $this->_getParam('username');
        $password = $this->_getParam('password');

        $decorator = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Default_User_Model_User'
        );

        $user = $decorator->getUserForUserNameCredentialsAsEntity(
            $username,
            md5($password)
        );

        if ($user === null || $user->getId() !== $id) {
            $this->view->error = "The user credentials you provided did not "
                               . "match the credentials of the currently logged "
                               . "in user.";
            return;
        }


        $receptionControllerNs = new Zend_Session_Namespace(
            Conjoon_Keys::SESSION_CONTROLLER_RECEPTION
        );

        $receptionControllerNs->locked = false;

        $this->view->success = true;
        $this->view->error   = null;
        $this->view->locked  = false;
    }

    /**
     * Called in a frequent interval when the AJAX driven application needs
     * to keep the users session alive,
     *
     */
    public function pingAction()
    {
        $this->view->success = true;
        $this->view->error   = null;
    }

    /**
     * Special action if the auth pre-dispatcher has found two different auth
     * tokens.
     * Will send a 401 error and force the user to completely restart the
     * application.
     *
     */
    public function authTokenFailureAction()
    {
        $this->_response->setHttpResponseCode(401);

        /**
         * @see Conjoon_Error
         */
        require_once 'Conjoon/Error.php';
        $error = new Conjoon_Error();

        $error->setCode(-1);
        $error->setLevel(Conjoon_Error::LEVEL_ERROR);
        $error->setFile(__FILE__);
        $error->setLine(__LINE__);

        $error->setMessage("Someone has signed in with your user credentials. Please sign in again.");
        $error->setType(Conjoon_Error::TOKEN_FAILURE);
        $this->view->tokenFailure = true;

        /**
         * @see Conjoon_Modules_Default_Registry_Facade
         */
        require_once 'Conjoon/Modules/Default/Registry/Facade.php';

        $this->view->title = Conjoon_Modules_Default_Registry_Facade
                             ::getInstance()
                             ->getValueForKeyAndUserId(
                                 '/base/conjoon/name',
                                 0
                             );

        /**
         * @see Zend_Registry
         */
        require_once 'Zend/Registry.php';

        /**
         * @see Conjoon_Keys
         */
        require_once 'Conjoon/Keys.php';

        // send the current logged in username with the response
        $auth = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $user = $auth->getIdentity()->getDto();

        /**
         * @todo create filter
         */
        unset($user->authToken);

        $this->view->user = $user;

        $this->view->success    = false;
        $this->view->error      = $error->getDto();
    }

    /**
     * Index action of the controller.
     * This action will be called whenever the application recognizes that the
     * user is not logged in anymore. This can be upon start, when the user has
     * to log in, or in another context, when the user uses the application and
     * his session gets lost.
     * Additionaly, this action will be called when the application detects that
     * the session of the user had been locked.
     * In all cases, the status code "401" will be send to
     * indicate that authorization is required.
     *
     * @see Conjoon_Controller_Plugin_Auth
     */
    public function indexAction()
    {
        $this->_response->setHttpResponseCode(401);

        require_once 'Zend/Session/Namespace.php';
        require_once 'Conjoon/Keys.php';

        $receptionControllerNs = new Zend_Session_Namespace(
            Conjoon_Keys::SESSION_CONTROLLER_RECEPTION
        );

        $isLocked = $receptionControllerNs->locked;

        require_once 'Conjoon/Error.php';
        $error = new Conjoon_Error();

        $error->setCode(-1);
        $error->setLevel(Conjoon_Error::LEVEL_ERROR);
        $error->setFile(__FILE__);
        $error->setLine(__LINE__);

        if ($isLocked === true) {
            $error->setMessage("Workbench is locked. You need to log in again to access this resource.");
            $error->setType(Conjoon_Error::LOCKED);
            $this->view->locked = true;
        } else {
            $error->setMessage("Authorization required. You need to log in to access this resource.");
            $error->setType(Conjoon_Error::AUTHORIZATION);
            $this->view->authorized = false;
        }

        /**
         * @see Conjoon_Modules_Default_Registry_Facade
         */
        require_once 'Conjoon/Modules/Default/Registry/Facade.php';

        $this->view->title = Conjoon_Modules_Default_Registry_Facade::getInstance()
                             ->getValueForKeyAndUserId(
                                 '/base/conjoon/name',
                                 $this->_helper->registryAccess()->getUserId()
                             );

        $this->view->success = false;
        $this->view->error   = $error->getDto();

    }

    /**
     * Logout action of the controller.
     * Logs a user completely out of the application.
     * This method may also be called if there is currently no session active/no
     * user logged in.
     */
    public function logoutAction()
    {
        $this->clearInformationForAutoLogin();

        Zend_Session::destroy(true, true);

        $this->view->success = true;
        $this->view->error   = null;
    }

    public function processAction()
    {
        require_once 'Conjoon/Auth/Adapter/Db.php';

        /**
         * @todo Filter username and password!
         */
        $username        = $this->_getParam('username');
        $password        = $this->_getParam('password');
        $rememberMe      = (bool) $this->_getParam('rememberMe');
        $lastUserRequest = (int)$this->_getParam('lastUserRequest');

        // Special case - the app was started and the user wants to re-login
        // since his session was lost. Check if the user object as returned by the
        // data storage has a property lastLogin which may not be greater than
        // the "lastUserRequest"-parameter - if that is teh case, most likely another
        // user has logged in so the user has to completely restart the application -
        // a redirect to the base url will happen
        if ($lastUserRequest) {
            /**
             * @see Conjoon_Modules_Default_User_Model_User
             */
            require_once 'Conjoon/Modules/Default/User/Model/User.php';
            $userTable = new Conjoon_Modules_Default_User_Model_User();

            /**
             * @see Conjoon_BeanContext_Decorator
             */
            require_once 'Conjoon/BeanContext/Decorator.php';
            $decorator = new Conjoon_BeanContext_Decorator($userTable);
            $userDto = $decorator->getUserForUserNameCredentialsAsDto($username, md5($password));

            if ($userDto && $lastUserRequest <= $userDto->lastLogin) {
                // special case - send an auth token failure with the response
                $this->_response->setHttpResponseCode(401);

                /**
                 * @see Conjoon_Error
                 */
                require_once 'Conjoon/Error.php';
                $error = new Conjoon_Error();

                $error->setCode(-1);
                $error->setLevel(Conjoon_Error::LEVEL_ERROR);
                $error->setFile(__FILE__);
                $error->setLine(__LINE__);

                $error->setMessage("Someone has signed in with your user credentials. Please sign in again.");
                $error->setType(Conjoon_Error::TOKEN_FAILURE);
                $this->view->tokenFailure = true;

                /**
                 * @todo create filter
                 */
                unset($userDto->authToken);

                $this->view->user = $userDto;

                $this->view->success    = false;
                $this->view->error      = $error->getDto();

                return;
            }
        }

        $auth        = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $authAdapter = new Conjoon_Auth_Adapter_Db(array(
            'username'    => $username,
            'password'    => $password,
            'remember_me' => $rememberMe
        ));


        // if the result is valid, the return value of the adapter will
        // be stored automatically in the supplied storage object
        // from the auth object
        $result = $auth->authenticate($authAdapter);

        if ($result->isValid()) {

            $user = $result->getIdentity();

            if ($rememberMe && $user->getRememberMeToken() != null) {
                $this->setAutoLoginCookies(
                    md5($user->getUserName()), $user->getRememberMeToken(), time() + 2592000
                );
            }

            $this->view->success = true;
       } else {
            $this->view->error   = 'Wrong username or password';
            $this->view->success = false;
        }
    }

    /**
     * Helper function for setting the auto login cookies.
     *
     * @param string $name md5 hashed user name
     * @param string $token the remember_me_token
     * @param int    $expires time (in unix timestamp) when the cookies expire.
     *                        use previous time to usnet cookies
     */
    protected function setAutoLoginCookies($name, $token, $expires) {

        $host = $_SERVER['HTTP_HOST'];

        /**
         * @see Conjoon_Keys
         */
        require_once 'Conjoon/Keys.php';

        setcookie(
            Conjoon_Keys::COOKIE_REMEMBERME_UNAME, $name,
            $expires, '/', $host
        );
        setcookie(
            Conjoon_Keys::COOKIE_REMEMBERME_TOKEN, $token,
            $expires, '/', $host
        );
    }

    /**
     * Helper function to clear related auto login information
     * for the currently signed in user.
     * ;ust be called before session gets invalidated.
     */
    protected function clearInformationForAutoLogin() {

        /**
         * @see Zend_Registry
         */
        require_once 'Zend/Registry.php';

        /**
         * @see Conjoon_Keys
         */
        require_once 'Conjoon/Keys.php';

        // send the current logged in username with the response
        $auth = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        if ($auth->getIdentity() && $auth->getIdentity()->getDto()) {
            $user = $auth->getIdentity()->getDto();

            /**
             * @see Conjoon_Modules_Default_User_Model_User
             */
            require_once 'Conjoon/Modules/Default/User/Model/User.php';

            $userTable = new Conjoon_Modules_Default_User_Model_User();
            $userTable->clearAutoLoginInformationForUserId($user->id);
        }

        $this->setAutoLoginCookies("", "", time() - 3600);
    }

}
