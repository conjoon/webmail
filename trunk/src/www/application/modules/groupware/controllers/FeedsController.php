<?php
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

/**
 * @see Zend_Controller_Action
 */
require_once 'Zend/Controller/Action.php';

/**
 * @see Zend_Http_Client
 */
require_once 'Zend/Http/Client.php';


class Groupware_FeedsController extends Zend_Controller_Action {

    const CONTEXT_JSON = 'json';

    /**
     * Inits this controller and sets the context-switch-directives
     * on the various actions.
     *
     */
    public function init()
    {
        $contextSwitch = $this->_helper->contextSwitch();

        $contextSwitch->addActionContext('is.feed.address.valid', self::CONTEXT_JSON)
                      ->addActionContext('get.feed.items', self::CONTEXT_JSON)
                      ->addActionContext('get.feed.accounts', self::CONTEXT_JSON)
                      ->addActionContext('set.item.read', self::CONTEXT_JSON)
                      ->addActionContext('add.feed', self::CONTEXT_JSON)
                      ->addActionContext('update.accounts', self::CONTEXT_JSON)
                      ->addActionContext('get.feed.content', self::CONTEXT_JSON)
                      ->initContext();
    }

// -------- items
    /**
     * Returns all feed items out of the database belonging to the current user,
     * and does also query all accounts for new feed items.
     * Feed items usually won't have a feed body.
     * On each manual refresh of the store and on the first startup of the store,
     * the client sends the parameter "removeold" set to "true", which tells the model
     * to wipe all old feed entries out of the database, based on the configured
     * "deleteInterval"-property in the according account.
     *
     */
    public function getFeedItemsAction()
    {
        require_once 'Conjoon/Keys.php';
        require_once 'Zend/Feed.php';
        require_once 'Conjoon/Modules/Groupware/Feeds/Item/Filter/Item.php';

        require_once 'Conjoon/BeanContext/Decorator.php';
        $model = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Feeds_Account_Model_Account'
        );
        $itemModel = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Feeds_Item_Model_Item'
        );

        $itemResponseFilter = new Conjoon_Modules_Groupware_Feeds_Item_Filter_Item(
            array(),
            Conjoon_Filter_Input::CONTEXT_RESPONSE
        );

        $filter = new Conjoon_Modules_Groupware_Feeds_Item_Filter_Item(
            $_POST,
            Conjoon_Filter_Input::CONTEXT_UPDATE
        );
        $filteredData = $filter->getProcessedData();
        $removeOld = $filteredData['removeold'];
        $timeout   = $filteredData['timeout'];

        $auth   = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $userId = $auth->getIdentity()->getId();

        $time = time();

        $accounts = $model->getAccountsToUpdateAsDto($userId, $time);

        $updatedAccounts = array();
        $insertedItems   = array();
        $len             = count($accounts);

        $secTimeout = $timeout/1000;
        $defTimeout = -1;
        // compute the timeout for the connections. Filter should have set the default
        // timeout to 30000 ms if the timeout param was not submitted
        // we need to compare this with the max_execution_time of the php installation
        // and take action in case the requestTimeout exceeds it, so each account will have
        // a reduced timeout, just in case (the configured timeout won't be considered then)
        if ($len > 0 && $secTimeout >= ini_get('max_execution_time')) {
            $defTimeout = (int)round(ini_get('max_execution_time')/$len);

            // if $defTimeout is less than 1, we will not try to load any feeds, or else
            // no response will ge through to the client
            if ($defTimeout < 1) {
                $len = 0;
            }
        }

        for ($i = 0; $i < $len; $i++) {
            // set requestTimeout to default if necessary
            if ($defTimeout != -1) {
                $accounts[$i]->requestTimeout = $defTimeout;
            }
            try {
                // set the client for each account so it can be configured
                // with the timeout. In case the sum of all timeouts exceeds
                // the max_execution_time of the PHP installation, each
                // request will be configured with the same timeout so the script
                // has enough time to finish
                Zend_Feed::setHttpClient(new Zend_Http_Client(
                    null, array('timeout' => $accounts[$i]->requestTimeout - 2)
                ));

                $import = Zend_Feed::import($accounts[$i]->uri);
                $items = $this->_importFeedItems($import, $accounts[$i]->id);
                for ($a = 0, $lena = count($items); $a < $lena; $a++) {
                    $items[$a]['saved_timestamp'] = time();
                    $added = $itemModel->addItemIfNotExists($items[$a], $accounts[$i]->id);
                    if ($added !== 0 && !$removeOld) {
                        $items[$a]['name'] = $accounts[$i]->name;
                        $items[$a]['id']   = $added;
                        Conjoon_Util_Array::camelizeKeys($items[$a]);
                        $itemResponseFilter->setData($items[$a]);
                        $insertedItems[] = $itemResponseFilter->getProcessedData();
                    }
                }
            } catch (Exception $e) {
                // ignore
            }
            $updatedAccounts[$accounts[$i]->id] = true;
        }

        // set the last updated timestamp for the accounts
        if (!empty($updatedAccounts)) {
            $model->setLastUpdated(array_keys($updatedAccounts), $time);
        }

        if ($removeOld) {
            $model->deleteOldFeedItems($userId);
            $items = $this->_getFeedItems();
        } else {
            // send all items that where added during this request
            // to the client
            $items = $insertedItems;
        }

        $this->view->success = true;
        $this->view->items   = $items;
        $this->view->error   = null;
    }

// -------- accounts

    /**
     * Adds another feed-account for the user.
     * This method will store the account-settings for the feed and immediately
     * store all items related to it. The items itself will be returned with the
     * view variable "items", the account will be available in the view-variable
     * "account".
     */
    public function addFeedAction()
    {
        require_once 'Zend/Feed.php';
        require_once 'Conjoon/Util/Array.php';
        require_once 'Conjoon/Keys.php';
        require_once 'Conjoon/BeanContext/Inspector.php';
        require_once 'Conjoon/Modules/Groupware/Feeds/Account/Model/Account.php';
        require_once 'Conjoon/Modules/Groupware/Feeds/Item/Model/Item.php';
        require_once 'Conjoon/Modules/Groupware/Feeds/Account/Filter/Account.php';

        $model  = new Conjoon_Modules_Groupware_Feeds_Account_Model_Account();

        $auth   = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $userId = $auth->getIdentity()->getId();

        $classToCreate = 'Conjoon_Modules_Groupware_Feeds_Account';

        $this->view->success = true;
        $this->view->error = null;

        try {
            $filter = new Conjoon_Modules_Groupware_Feeds_Account_Filter_Account(
                $_POST,
                Conjoon_Filter_Input::CONTEXT_CREATE
            );
            $filteredData = $filter->getProcessedData();

            $import = Zend_Feed::import($filteredData['uri']);

            require_once 'Zend/Filter/HtmlEntities.php';
            $htmlEntities = new Zend_Filter_HtmlEntities(ENT_COMPAT, 'UTF-8');
            $filteredData['title'] = $htmlEntities->filter($import->title());
            $filteredData['link']  = $import->link();

            // atom feeds may have more than 1 link tag. Simply take the first one's
            // node value
            if (!is_string($filteredData['link'])) {
                if (isset($filteredData['link'][0])) {
                    if (is_object($filteredData['link'][0])) {
                        $cls = get_class($filteredData['link'][0]);
                        if (strtolower($cls) === 'domelement'){
                            $link = @$filteredData['link'][0]->firstChild->data;
                            if (!$link) {
                                $link = $filteredData['link'][0]->getAttribute('href');
                            }

                            $filteredData['link'] = $link;
                        }
                    }
                }

                if (!is_string($filteredData['link']) || $filteredData['link'] == "") {
                    // fallback - use the uri
                    $filteredData['link'] = $filteredData['uri'];
                }
            }

            $filteredData['description'] = $import->description();
            $data = $filteredData;
            Conjoon_Util_Array::underscoreKeys($data);
            $data['user_id'] = $userId;
            $data['last_updated'] = time();

            $insertId = $model->addAccount($data);
            if ($insertId <= 0) {
                $this->view->success = false;
                return;
            }
            $filteredData['id'] = $insertId;
            $this->view->account = Conjoon_BeanContext_Inspector::create(
                $classToCreate,
                $filteredData
            )->getDto();

            $itemModel = new Conjoon_Modules_Groupware_Feeds_Item_Model_Item();

            $data = $this->_importFeedItems($import, $filteredData['id']);

            for ($i = 0, $len = count($data); $i < $len; $i++) {
                $itemModel->insert($data[$i]);
            }

            $this->view->items = $this->_getFeedItems($filteredData['id']);

        } catch (Zend_Filter_Exception $e) {
            require_once 'Conjoon/Error.php';
            $error = Conjoon_Error::fromFilter($filter, $e);
            $accountData = $_POST;
            $this->view->account = Conjoon_BeanContext_Inspector::create(
                $classToCreate,
                $_POST
            )->getDto();
            $this->view->success = false;
            $this->view->error = $error->getDto();
        }
    }

    /**
     * Action for saving account configuratiom
     * 2 Arrays will be submitted, one named "deleted", holding all id's of the accounts that
     * should be removed from the store, and one named "updated", holding all objects
     * representing the accounts that should be updated.
     * Depending on the context, either json-encoded strings will be available, or plain
     * arrays.
     */
    public function updateAccountsAction()
    {
        require_once 'Conjoon/Modules/Groupware/Feeds/Account/Filter/Account.php';
        require_once 'Conjoon/Util/Array.php';
        require_once 'Conjoon/Modules/Groupware/Feeds/Account/Model/Account.php';

        $toDelete      = array();
        $toUpdate      = array();
        $deletedFailed = array();
        $updatedFailed = array();

        $model   = new Conjoon_Modules_Groupware_Feeds_Account_Model_Account();

        $data  = array();
        $error = null;

        if ($this->_helper->contextSwitch()->getCurrentContext() == self::CONTEXT_JSON) {
            require_once 'Zend/Json.php';
            $toDelete = Zend_Json::decode($_POST['deleted'], Zend_Json::TYPE_ARRAY);
            $toUpdate = Zend_Json::decode($_POST['updated'], Zend_Json::TYPE_ARRAY);
        }

        for ($i = 0, $len = count($toDelete); $i < $len; $i++) {
            $affected = $model->deleteAccount($toDelete[$i]);
            if (!$affected) {
                $deletedFailed[] = $toDelete[$i];
            }
        }

        for ($i = 0, $len = count($toUpdate); $i < $len; $i++) {
            $_ = $toUpdate[$i];
            $filter = new Conjoon_Modules_Groupware_Feeds_Account_Filter_Account(
                $_,
                Conjoon_Filter_Input::CONTEXT_UPDATE
            );
            try {
                $data[$i] = $filter->getProcessedData();
                Conjoon_Util_Array::underscoreKeys($data[$i]);
            } catch (Zend_Filter_Exception $e) {
                 require_once 'Conjoon/Error.php';
                 $error = Conjoon_Error::fromFilter($filter, $e);
                 $this->view->success = false;
                 $this->view->updatedFailed = array($_['id']);
                 $this->view->deletedFailed = $deletedFailed;
                 $this->view->error = $error->getDto();
                 break;
            }
        }

        if ($error === null) {
            for ($i = 0, $len = count($data); $i < $len; $i++) {
                $id = $data[$i]['id'];
                unset($data[$i]['id']);
                $affected = $model->updateAccount($id, $data[$i]);
                if (!$affected) {
                    $updatedFailed[] = $id;
                }
            }

            $this->view->success        = empty($updatedFailed) ? true : false;
            $this->view->updatedFailed = $updatedFailed;
            $this->view->deletedFailed = $deletedFailed;
            $this->view->error         = null;
        }
    }

    /**
     * Queries and assigns all feed accounts belonging to the currently logged in
     * user to the view
     */
    public function getFeedAccountsAction()
    {
        require_once 'Conjoon/Keys.php';
        $user = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT)->getIdentity();

        require_once 'Conjoon/BeanContext/Decorator.php';
        $decoratedModel = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Feeds_Account_Model_Account'
        );

        $data = $decoratedModel->getAccountsForUserAsDto($user->getId());

        $this->view->success  = true;
        $this->view->accounts = $data;
        $this->view->error    = null;
    }


    /**
     * Checks wether the given uri points to a valid feed container.
     *
     */
    public function isFeedAddressValidAction()
    {
        require_once 'Zend/Feed.php';

        $uri = $_POST['uri'];

        $feed = null;
        $this->view->success = true;
        $this->view->error   = null;
        try {
            $feed = Zend_Feed::import($uri);
        } catch (Zend_Feed_Exception $e) {
            $this->view->success = false;
        }
    }

    /**
     * Flags a specific feed item as either read or unread, based on the passed
     * arguments.
     * Data will be comin via post, whereas in json context a json-encoded
     * string will be submitted, which can be found in the $_POST var keyed
     * with "json".
     * The method will never return an error itself, as the operation on teh udnerlying
     * datastore will not affect Uinteraction critically.
     */
    public function setItemReadAction()
    {
        if ($this->_helper->contextSwitch()->getCurrentContext() == self::CONTEXT_JSON) {
            require_once 'Zend/Json.php';
            $toUpdate = Zend_Json::decode($_POST['json'], Zend_Json::TYPE_ARRAY);
        }

        require_once 'Conjoon/Modules/Groupware/Feeds/Item/Filter/Item.php';
        require_once 'Conjoon/Modules/Groupware/Feeds/Item/Model/Item.php';
        require_once 'Conjoon/Util/Array.php';

        $model = new Conjoon_Modules_Groupware_Feeds_Item_Model_Item();

        $filter = new Conjoon_Modules_Groupware_Feeds_Item_Filter_Item(
            array(),
            Conjoon_Modules_Groupware_Feeds_Item_Filter_Item::CONTEXT_READ
        );

        $read   = array();
        $unread = array();
        for ($i = 0, $len = count($toUpdate); $i < $len; $i ++) {
            $filter->setData($toUpdate[$i]);
            $data = $filter->getProcessedData();
            if ($data['isRead']) {
                $read[] = $data['id'];
            } else {
                $unread[] = $data['id'];
            }
        }

        $model->setItemRead($read,   true);
        $model->setItemRead($unread, false);

        $this->view->success = true;
        $this->view->error   = null;

    }

    /**
     * Returns the feed item (dto) with it's content.
     *
     */
    public function getFeedContentAction()
    {
        /**
         * @todo filter incoming data
         */
        $id = $this->_request->getParam('id', 0);

        /**
         * @see Conjoon_Keys
         */
        require_once 'Conjoon/Keys.php';

        /**
         * @see Conjoon_Builder_Factory
         */
        require_once 'Conjoon/Builder/Factory.php';

        $item = Conjoon_Builder_Factory::getBuilder(
            Conjoon_Keys::CACHE_FEED_ITEM,
            Zend_Registry::get(Conjoon_Keys::REGISTRY_CONFIG_OBJECT)->toArray()
        )->get(array('id' => $id));

        if ($item == null) {
            /**
             * @see Conjoon_Error_Factory
             */
            require_once 'Conjoon/Error/Factory.php';

            $this->view->success = false;
            $this->view->item    = null;
            $this->view->error   = Conjoon_Error_Factory::createError(
                "The requested feed item was not found on the server.",
                Conjoon_Error::LEVEL_ERROR,
                Conjoon_Error::DATA
            )->getDto();
        } else {
            $this->view->success = true;
            $this->view->item    = $item;
            $this->view->error   = null;
        }

    }



// -------- helper

    /**
     * Imports all feed items from a given cross domain source.
     */
    private function _importFeedItems($import, $accountId)
    {
        require_once 'Zend/Date.php';
        require_once 'Conjoon/Util/Array.php';
        require_once 'Conjoon/Modules/Groupware/Feeds/Item/Filter/Item.php';

        /**
         * @see Conjoon_Filter_DateFormat
         */
        require_once 'Conjoon/Filter/DateFormat.php';

        $dateInputFormat = Zend_Date::TIMESTAMP;

        switch (get_class($import)) {
            case 'Zend_Feed_Atom':
                $dateInputFormat = Zend_Date::ATOM;
            break;

            case'Zend_Feed_Rss':
                $dateInputFormat = Zend_Date::RSS;
            break;
        }

        $dateFilter = new Conjoon_Filter_DateFormat('Y-m-d H:i:s', $dateInputFormat);

        $data = array();

        foreach ($import as $item) {

            $itemData = array();
            $itemData['groupwareFeedsAccountsId'] = $accountId;

            $itemData['title'] = $item->title();

            // author
            if ($author = $item->author()) {
                $itemData['author'] = $author;
            } else if ($author = $item->creator()) {
                $itemData['author'] = $author;
            }

             // description
            if ($description = $item->description()) {
                $itemData['description'] = $description;
            } else if ($description = $item->summary()) {
                $itemData['description'] = $description;
            } else {
                $itemData['description'] = $itemData['title'];
            }

            // content
            if ($content = $item->content()) {
                $itemData['content'] = $content;
            } else if ($itemData['description']) {
                $itemData['content'] = $itemData['description'];
            }

            // link
            if ($link = $item->link() && !is_object($item->link())) {
                $itemData['link'] = $link;
            } else if (isset($item->link['href']) && $link = $item->link['href']) {
                $itemData['link'] = $link;
            } else if ($link = $item->link('alternate')) {
                $itemData['link'] = $link;
            } else if ($link = $item->link(0)) {
                $itemData['link'] = $link;
            }

            // guid
            if ($link = $item->id()) {
                $itemData['guid'] = $link;
            } else if ($link = $item->guid()) {
                $itemData['guid'] = $link;
            } else {
                $itemData['guid'] = $itemData['link'];
            }

            // pubDate

            if ($pubDate = $item->updated()) {
                $date = $pubDate;
            } else if ($pubDate = $item->pubDate()) {
                $date = $pubDate;
            } else if ($pubDate = $item->date()) {
                $date = $pubDate;
            } else {
                $date = new Zend_Date();
            }

            $itemData['pubDate'] = $dateFilter->filter($date);

            $itemData['savedTimestamp'] = time();

            $filter = new Conjoon_Modules_Groupware_Feeds_Item_Filter_Item(
                $itemData,
                Conjoon_Filter_Input::CONTEXT_CREATE
            );
            $fillIn = $filter->getProcessedData();
            Conjoon_Util_Array::underscoreKeys($fillIn);
            $data[] = $fillIn;
        }

        return $data;
    }

    /**
     * Read out all feeds without the field 'content'
     *
     * @param integer $accountId the id of the account to fetch the feed items for,
     * or null to fetch all feed items for the currently logged in user
     */
    private function _getFeedItems($accountId = null)
    {
        require_once 'Conjoon/Keys.php';
        require_once 'Conjoon/BeanContext/Decorator.php';

        require_once 'Conjoon/Modules/Groupware/Email/Item/Filter/ItemResponse.php';
        $itemResponseFilter = new Conjoon_Modules_Groupware_Feeds_Item_Filter_Item(
            array(),
            Conjoon_Filter_Input::CONTEXT_RESPONSE
        );

        $model = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Feeds_Account_Model_Account'
        );
        $itemModel = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Feeds_Item_Model_Item',
            $itemResponseFilter
        );

        $user = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT)->getIdentity();
        if ($accountId === null) {
            $data = $model->getAccountsForUserAsDto($user->getId());
        } else {
            $data = array($model->getAccountAsDto($accountId));
        }

        $accounts = array();
        $items    = array();
        for ($i = 0, $len = count($data); $i < $len; $i++) {
            $tmpItems = $itemModel->getItemsForAccountAsDto($data[$i]->id);
            for ($a = 0, $len2 = count($tmpItems); $a < $len2; $a++) {
                $items[] = $tmpItems[$a];
            }
        }

        return $items;
    }

    /**
     * Helper for stripping not needed information from an instance of
     * Conjoon_Modules_Groupware_Feeds_ItemDto for sending it to the client.
     *
     */
    private function _transformItemDto(Conjoon_Modules_Groupware_Feeds_Item_Dto $item)
    {
        $item->content = null;
        unset($item->guid);
        $item->description = $item->description ? substr($item->description, 0, 128).'...' : '';
    }

}
?>