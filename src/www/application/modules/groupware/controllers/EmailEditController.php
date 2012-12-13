<?php
/**
 * conjoon
 * (c) 2002-2012 siteartwork.de/conjoon.org
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
 * Zend_Controller_Action
 */
require_once 'Zend/Controller/Action.php';

/**
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
class Groupware_EmailEditController extends Zend_Controller_Action {

    const CONTEXT_JSON = 'json';

    /**
     * Inits this controller and sets the context-switch-directives
     * on the various actions.
     *
     */
    public function init()
    {
        $conjoonContext = $this->_helper->conjoonContext();

        $conjoonContext->addActionContext('get.recipient',  self::CONTEXT_JSON)
                       ->addActionContext('get.draft',      self::CONTEXT_JSON)
                       ->addActionContext('save.draft',     self::CONTEXT_JSON)
                       ->addActionContext('move.to.outbox', self::CONTEXT_JSON)
                       ->initContext();
    }

    /**
     * Looks up a recipient from the contacts table and returns it to the view.
     * The value submitted is a fragment of either a contacts real name or the
     * email address of the contact.
     *
     *
     */
    public function getRecipientAction()
    {
        require_once 'Conjoon/Util/Array.php';
        require_once 'Conjoon/Keys.php';
        require_once 'Conjoon/BeanContext/Inspector.php';
        require_once 'Conjoon/Modules/Groupware/Contact/Item/Model/Item.php';

        $query = isset($_POST['query']) ? $_POST['query'] : '';

        if (trim($query) == "") {
            $this->view->success = true;
            $this->view->error   = null;
            $this->view->matches = array();
            return;
        }

        $model  = new Conjoon_Modules_Groupware_Contact_Item_Model_Item();

        $auth   = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $userId = $auth->getIdentity()->getId();

        $contacts = $model->getContactsByNameOrEmailAddress($userId, $query);

        $response = array();

        foreach ($contacts as $contact) {
            $address = $contact['email_address'];
            $name    = ($contact['first_name']
                       ? $contact['first_name'] .' '
                       : '')
                       . ($contact['last_name']
                       ? $contact['last_name'] .' '
                       : '');

            $response[] = array(
                'name'        => $name ? $name : '',
                'address'     => $address,
                'fullAddress' => $name ? $name . '<' . $address . '>' : $address
            );
        }

        $this->view->success = true;
        $this->view->error   = null;
        $this->view->matches = $response;
    }

    /**
     * A draft can be loaded from the database if an id was supplied
     * or filled with dummy data if no id was supplied. If no id was supplied,
     * the user wants to create a new email. In this case, the id defaults to
     * -1. If the user requests to save the draft later on, the id will be updated
     * to the value of the auto_increment field of the table.
     * Along with an id the application will need a folder_id so it can tell whether
     * an existing view has to be updated if this draft was edited and the folder
     * is currently visible.
     * Note, that getDraft will also be executed when the user wants to reply to
     * an email or forward an email. in this case, the id defaults to the email to
     * which the user wants to forward/ reply to.
     *
     * The method awaits 4 POST parameters:
     * id - the original message to reply to OR the id of the draft that is being
     * edited
     * type - the context the draft is in: can be either "new", "forward",
     *        "reply", "reply_all" or "edit"
     * name:    the name of an recipient to send this email to
     * address: the address of an recipient to send this email to. If that value is not
     *          empty. id will be set to -1 and type will be set to new. If address equals
     *          to name or if name is left empty, only the address will be used to send the
     *          email to. Address is given presedence in any case
     */
    public function getDraftAction()
    {
        if ($this->_helper->conjoonContext()->getCurrentContext() !=
            self::CONTEXT_JSON) {
            /**
             * see Conjoon_Controller_Action_InvalidContextException
             */
            require_once 'Conjoon/Controller/Action/InvalidContextException.php';

            throw new Conjoon_Controller_Action_InvalidContextException(
                "Invalid context for action, expected \""
                    . self::CONTEXT_JSON
                    . "\", got \""
                    . $this->_helper->conjoonContext()->getCurrentContext()
                    ."\""
            );
        }

        $path = $_POST['path'];

        // check if folder is remote folder
        /**
         * @see Conjoon_Text_Parser_Mail_MailboxFolderPathJsonParser
         */
        require_once 'Conjoon/Text/Parser/Mail/MailboxFolderPathJsonParser.php';

        $parser = new Conjoon_Text_Parser_Mail_MailboxFolderPathJsonParser();

        $pathInfo = $parser->parse($path);

        /**
         * @see Conjoon_Modules_Groupware_Email_Folder_Facade
         */
        require_once 'Conjoon/Modules/Groupware/Email/Folder/Facade.php';

        $facade = Conjoon_Modules_Groupware_Email_Folder_Facade::getInstance();

        if (!empty($pathInfo) && $facade->isRemoteFolder($pathInfo['rootId'])) {
            return $this->getDraftFromRemoteServer($_POST['id'], $path, $_POST['type']);
        }

        /**
         * @see Conjoon_Keys
         */
        require_once 'Conjoon/Keys.php';

        /**
         * @see Conjoon_BeanContext_Inspector
         */
        require_once 'Conjoon/BeanContext/Inspector.php';

        /**
         * @see Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse
         */
        require_once 'Conjoon/Modules/Groupware/Email/Draft/Filter/DraftResponse.php';

        /**
         * @see Conjoon_Modules_Groupware_Email_Account_Model_Account
         */
        require_once 'Conjoon/Modules/Groupware/Email/Account/Model/Account.php';

        /**
         * @see Conjoon_Util_Array
         */
        require_once 'Conjoon/Util/Array.php';

        $auth   = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $userId = $auth->getIdentity()->getId();

        $id   = (int)$_POST['id'];
        $type = (string)$_POST['type'];

        $accountModel = new Conjoon_Modules_Groupware_Email_Account_Model_Account();

        // create a new draft so that the user is able to write an email from scratch!
        if ($id <= 0) {

            /**
             * @see Conjoon_Modules_Groupware_Email_Draft
             */
            require_once 'Conjoon/Modules/Groupware/Email/Draft.php';

            $standardId   = $accountModel->getStandardAccountIdForUser($userId);

            if ($standardId == 0) {
                require_once 'Conjoon/Error.php';
                $error = new Conjoon_Error();
                $error = $error->getDto();;
                $error->title = 'Error while opening draft';
                $error->message = 'Please configure an email account first.';
                $error->level = Conjoon_Error::LEVEL_ERROR;

                $this->view->draft   = null;
                $this->view->success = false;
                $this->view->error   = $error;

                return;
            }

            $post = $_POST;

            Conjoon_Util_Array::apply($post, array(
                'groupwareEmailAccountsId' => $standardId,
                'groupwareEmailFoldersId'  => -1
            ));

            $draftFilter = new Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse(
                $post,
                Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse::CONTEXT_NEW
            );

            $data = $draftFilter->getProcessedData();

            $draft = Conjoon_BeanContext_Inspector::create(
                'Conjoon_Modules_Groupware_Email_Draft',
                $data
            );

            $this->view->success = true;
            $this->view->error   = null;
            $this->view->draft   = $draft->getDto();
            $this->view->type    = $type;

            return;
        }

        // load an email to edit, to reply or to forward it
        /**
         * @see Conjoon_Modules_Groupware_Email_Draft_Model_Draft
         */
        require_once 'Conjoon/Modules/Groupware/Email/Draft/Model/Draft.php';

        $draftModel = new Conjoon_Modules_Groupware_Email_Draft_Model_Draft();
        $draftData = $draftModel->getDraft($id, $userId, $type);

        if (empty($draftData)) {
            require_once 'Conjoon/Error.php';
            $error = new Conjoon_Error();
            $error = $error->getDto();;
            $error->title = 'Error while opening draft';
            $error->message = 'Could not find the referenced draft.';
            $error->level = Conjoon_Error::LEVEL_ERROR;

            $this->view->draft   = null;
            $this->view->success = false;
            $this->view->error   = $error;

            return;
        }

        $context = "";

        switch ($type) {
            case 'reply':
                $context = Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse::CONTEXT_REPLY;
            break;

            case 'reply_all':
                $context = Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse::CONTEXT_REPLY_ALL;
            break;

            case 'forward':
                $context = Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse::CONTEXT_FORWARD;
            break;

            case 'edit':
                $context = Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse::CONTEXT_EDIT;
            break;

            default:
                throw new Exception("Type $type not supported.");
            break;
        }

        Conjoon_Util_Array::camelizeKeys($draftData);

        $addresses = $accountModel->getEmailAddressesForUser($userId);

        $draftData['userEmailAddresses'] = $addresses;

        $draftFilter = new Conjoon_Modules_Groupware_Email_Draft_Filter_DraftResponse(
            $draftData,
            $context
        );

        $data         = $draftFilter->getProcessedData();
        $templateData = $data;

        // needed for draft forward because of Bean_Inspector
        unset($data['userEmailAddresses']);
        unset($data['from']);
        unset($data['replyTo']);

        if ($type == 'forward') {
            $data['to']  = array();
            $data['cc']  = array();
        }

        // convert email addresses
        /**
         * @see Conjoon_Modules_Groupware_Email_Address
         */
        require_once 'Conjoon/Modules/Groupware/Email/Address.php';

        $to   = array();
        $cc   = array();
        $bcc  = array();
        foreach ($data['to'] as $add) {
            $to[] = new Conjoon_Modules_Groupware_Email_Address($add);
        }
        foreach ($data['cc'] as $add) {
            $cc[] = new Conjoon_Modules_Groupware_Email_Address($add);
        }
        foreach ($data['bcc'] as $add) {
            $bcc[] = new Conjoon_Modules_Groupware_Email_Address($add);
        }
        $data['to']  = $to;
        $data['cc']  = $cc;
        $data['bcc'] = $bcc;

        $draft = Conjoon_BeanContext_Inspector::create(
            'Conjoon_Modules_Groupware_Email_Draft',
            $data
        )->getDto();

        if ($type == 'forward') {

            $applicationPath = $this->_helper->registryAccess()->getApplicationPath();

            /**
             * @see Conjoon_Text_PhpTemplate
             */
            require_once 'Conjoon/Text/PhpTemplate.php';

            /**
             * @see Conjoon_Filter_StringWrap
             */
            require_once 'Conjoon/Filter/StringWrap.php';

            /**
             * @see Zend_Filter_HtmlEntities
             */
            require_once 'Zend/Filter/HtmlEntities.php';

            $cfsw = new Conjoon_Filter_StringWrap('[Fwd: ', ']');
            $zfhe = new Zend_Filter_HtmlEntities(array(
                'quotestyle' => ENT_COMPAT,
                'charset'    => 'UTF-8'
            ));

            $draft->subject = $cfsw->filter($templateData['subject']);

            $templateData['subject']
                = $zfhe->filter($templateData['subject']);

            $phpTemplate = new Conjoon_Text_PhpTemplate(array(
                Conjoon_Text_PhpTemplate::PATH =>
                    $applicationPath .
                    '/templates/groupware/email/message.forward.phtml',
                Conjoon_Text_PhpTemplate::VARS => $templateData
            ));

            $draft->contentTextPlain = $phpTemplate->getParsedTemplate();
        }

        $this->view->success = true;
        $this->view->error   = null;
        $this->view->draft   = $draft;
        $this->view->type    = $type;
    }

    /**
     * Loads and returns a draft from a remote repository.
     *
     * @param string $id
     * @param string $path
     * @param string $type
     *
     */
    protected function getDraftFromRemoteServer($id, $path, $type)
    {
        /**
         * @see Zend_Registry
         */
        require_once 'Zend/Registry.php';

        /**
         *@see Conjoon_Keys
         */
        require_once 'Conjoon/Keys.php';

        $auth = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);

        /**
         * @see Conjoon_User_AppUser
         */
        require_once 'Conjoon/User/AppUser.php';

        $appUser = new \Conjoon\User\AppUser($auth->getIdentity());

        $entityManager = Zend_Registry::get(Conjoon_Keys::DOCTRINE_ENTITY_MANAGER);

        $mailFolderRepository =
            $entityManager->getRepository('\Conjoon\Data\Entity\Mail\DefaultMailFolderEntity');
        $messageFlagRepository =
            $entityManager->getRepository('\Conjoon\Data\Entity\Mail\DefaultMessageFlagEntity');
        $mailAccountRepository =
            $entityManager->getRepository('\Conjoon\Data\Entity\Mail\DefaultMailAccountEntity');

        $protocolAdaptee = new \Conjoon\Mail\Server\Protocol\DefaultProtocolAdaptee(
            $mailFolderRepository, $messageFlagRepository, $mailAccountRepository
        );

        /**
         * @see \Conjoon\Mail\Server\Protocol\DefaultProtocol
         */
        $protocol = new \Conjoon\Mail\Server\Protocol\DefaultProtocol(
            $protocolAdaptee
        );

        /**
         * @see \Conjoon\Mail\Server\DefaultServer
         */
        require_once 'Conjoon/Mail/Server/DefaultServer.php';

        $server = new \Conjoon\Mail\Server\DefaultServer($protocol);


        /**
         * @see \Conjoon\Mail\Client\Service\DefaultMessageServiceFacade
         */
        require_once 'Conjoon/Mail/Client/Service/DefaultMessageServiceFacade.php';

        $serviceFacade = new \Conjoon\Mail\Client\Service\DefaultMessageServiceFacade(
            $server, $mailAccountRepository, $mailFolderRepository
        );

        $result =  $serviceFacade->getMessageForReply(
            $this->_request->getParam('id'),
            $this->_request->getParam('path'),
            $appUser
        );

        $this->view->success = $result->isSuccess();
        $this->view->data    = $result->getData();
        $this->view->error      = null;
    }


    /**
     * Saves a draft into the database for later editing /sending.
     *
     * Incoming data will be filtered and then saved into the database.
     *
     *
     *
     */
    public function saveDraftAction()
    {
        /**
         * @see Conjoon_Modules_Groupware_Email_Draft_Filter_DraftInput
         */
        require_once 'Conjoon/Modules/Groupware/Email/Draft/Filter/DraftInput.php';

        try {
            // the filter will transform the "message" into bodyHtml and bodyText, depending
            // on the passed format. both will only be filled if format equals to "multipart"
            $filter = new Conjoon_Modules_Groupware_Email_Draft_Filter_DraftInput(
                $_POST,
                Conjoon_Modules_Groupware_Email_Draft_Filter_DraftInput::CONTEXT_DRAFT
            );
            $data = $filter->getProcessedData();
        } catch (Exception $e) {
             require_once 'Conjoon/Error.php';
             $error = Conjoon_Error::fromFilter($filter, $e);
             $this->view->success = false;
             $this->view->error   = $error->getDto();
             $this->view->item    = null;
             return;
        }

        /**
         * @see Conjoon_Modules_Groupware_Email_Address
         */
        require_once 'Conjoon/Modules/Groupware/Email/Address.php';

        /**
         * @see Conjoon_Modules_Groupware_Email_Draft
         */
        require_once 'Conjoon/Modules/Groupware/Email/Draft.php';

        /**
         * @see Conjoon_BeanContext_Inspector
         */
        require_once 'Conjoon/BeanContext/Inspector.php';

        // create the message object here
        $to  = array();
        $cc  = array();
        $bcc = array();

        $toString  = array();
        $ccString  = array();

        $postedAttachments = $data['attachments'];
        $data['attachments'] = array();

        $removeAttachmentIds = $data['removedAttachments'];
        unset($data['removedAttachments']);

        foreach ($data['cc'] as $dcc) {
            $add        = new Conjoon_Modules_Groupware_Email_Address($dcc);
            $cc[]       = $add;
            $toString[] = $add->__toString();
        }
        foreach ($data['bcc'] as $dbcc) {
            $add         = new Conjoon_Modules_Groupware_Email_Address($dbcc);
            $bcc[]       = $add;
        }
        foreach ($data['to'] as $dto) {
            $add        = new Conjoon_Modules_Groupware_Email_Address($dto);
            $to[]       = $add;
            $toString[] = $add->__toString();
        }

        $toString  = implode(', ', $toString);
        $ccString  = implode(', ', $ccString);

        $data['cc']  = $cc;
        $data['to']  = $to;
        $data['bcc'] = $bcc;

        // get the specified account for the user
        require_once 'Conjoon/BeanContext/Decorator.php';
        require_once 'Conjoon/Keys.php';

        $accountDecorator = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Email_Account_Model_Account'
        );

        $auth   = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $userId = $auth->getIdentity()->getId();

        $account = $accountDecorator->getAccountAsEntity($data['groupwareEmailAccountsId'], $userId);

        // no account found?
        if (!$account) {
            require_once 'Conjoon/Error.php';
            $error = new Conjoon_Error();
            $error = $error->getDto();;
            $error->title = 'Error while saving email';
            $error->message = 'Could not find specified account.';
            $error->level = Conjoon_Error::LEVEL_ERROR;
            $this->view->error   = $error;
            $this->view->success = false;
            $this->view->item    = null;
            return;
        }

        $draft = Conjoon_BeanContext_Inspector::create(
                'Conjoon_Modules_Groupware_Email_Draft',
                $data,
                true
        );

        // check whether we need to apply attachments for a previously saved
        // draft
        if ($draft->getId() > 0) {
            /**
             * @see Conjoon_Modules_Groupware_Email_Attachment_Filter_AttachmentResponse
             */
            require_once 'Conjoon/Modules/Groupware/Email/Attachment/Filter/AttachmentResponse.php';
            $attDecorator = new Conjoon_BeanContext_Decorator(
                'Conjoon_Modules_Groupware_Email_Attachment_Model_Attachment',
                new Conjoon_Modules_Groupware_Email_Attachment_Filter_AttachmentResponse(
                    array(),
                    Conjoon_Modules_Groupware_Email_Attachment_Filter_AttachmentResponse::CONTEXT_RESPONSE
                )
            );
            $atts = $attDecorator->getAttachmentsForItemAsEntity($draft->getId());
            $draft->setAttachments($atts);
        }

        /**
         * @see Conjoon_BeanContext_Decorator
         */
        require_once 'Conjoon/BeanContext/Decorator.php';

        /**
         * @see Conjoon_Modules_Groupware_Email_Item_Filter_ItemResponse
         */
        require_once 'Conjoon/Modules/Groupware/Email/Item/Filter/ItemResponse.php';

        /**
         * @see Conjoon_Modules_Groupware_Email_Item_Model_Item
         */
        require_once 'Conjoon/Modules/Groupware/Email/Item/Model/Item.php';

        $itemModel = new Conjoon_Modules_Groupware_Email_Item_Model_Item();

        $attachmentMap = array();
        $item = $itemModel->saveDraft(
            $draft, $account, $userId, $data['type'], $data['referencesId'],
            $postedAttachments, $removeAttachmentIds, $attachmentMap
        );

        if (!$item || empty($item)) {
            /**
             * @see Conjoon_Error
             */
            require_once 'Conjoon/Error.php';
            $error = new Conjoon_Error();
            $error = $error->getDto();;
            $error->title = 'Error while saving email';
            $error->message = 'The email could not be stored into the database.';
            $error->level = Conjoon_Error::LEVEL_ERROR;
            $this->view->error   = $error;
            $this->view->success = false;
            $this->view->item    = null;
            return;
        }

        /**
         * @see Conjoon_Util_Array
         */
        require_once 'Conjoon/Util/Array.php';

        Conjoon_Util_Array::camelizeKeys($item);

        $irFilter = new Conjoon_Modules_Groupware_Email_Item_Filter_ItemResponse(
            $item, Conjoon_Filter_Input::CONTEXT_RESPONSE
        );

        $item = $irFilter->getProcessedData();

        $item = Conjoon_BeanContext_Inspector::create(
            'Conjoon_Modules_Groupware_Email_Item',
            $item
        )->getDto();

        /**
         * @see Conjoon_Modules_Groupware_Email_Message_Facade
         */
        require_once 'Conjoon/Modules/Groupware/Email/Message/Facade.php';

        $emailRecord = Conjoon_Modules_Groupware_Email_Message_Facade::getInstance()
                       ->getMessage(
                            $item->id,
                            $this->_helper->registryAccess()->getUserId(),
                            true
                       );

        // silently add old ids to new ids from attachmentMap
        $attachments =& $emailRecord->attachments;

        foreach ($attachmentMap as $orgId => $newId) {
            for ($i = 0, $len = count($attachments); $i < $len; $i++) {
                if ($attachments[$i]->id == $newId) {
                    $attachments[$i]->oldId    = $orgId;
                }
            }
        }

        $this->view->error         = null;
        $this->view->success       = true;
        $this->view->item          = $item;
        $this->view->emailRecord   = $emailRecord;

    }

    /**
     * Saves a draft into the outbox folder of the user.
     */
    public function moveToOutboxAction()
    {
        /**
         * @see Conjoon_Modules_Groupware_Email_Draft_Filter_DraftInput
         */
        require_once 'Conjoon/Modules/Groupware/Email/Draft/Filter/DraftInput.php';

        $data = array();
        try {
            // the filter will transform the "message" into bodyHtml and bodyText, depending
            // on the passed format. both will only be filled if format equals to "multipart"
            $filter = new Conjoon_Modules_Groupware_Email_Draft_Filter_DraftInput(
                $_POST,
                Conjoon_Filter_Input::CONTEXT_CREATE
            );
            $data = $filter->getProcessedData();
        } catch (Exception $e) {
             require_once 'Conjoon/Error.php';
             $error = Conjoon_Error::fromFilter($filter, $e);
             $this->view->success = false;
             $this->view->error   = $error->getDto();
             $this->view->item    = null;
             return;
        }

        /**
         * @see Conjoon_Modules_Groupware_Email_Address
         */
        require_once 'Conjoon/Modules/Groupware/Email/Address.php';

        /**
         * @see Conjoon_Modules_Groupware_Email_Draft
         */
        require_once 'Conjoon/Modules/Groupware/Email/Draft.php';

        /**
         * @see Conjoon_BeanContext_Inspector
         */
        require_once 'Conjoon/BeanContext/Inspector.php';

        // create the message object here
        $to  = array();
        $cc  = array();
        $bcc = array();

        $toString  = array();
        $ccString  = array();

        $postedAttachments = $data['attachments'];
        $data['attachments'] = array();

        $removeAttachmentIds = $data['removedAttachments'];
        unset($data['removedAttachments']);

        foreach ($data['cc'] as $dcc) {
            $add        = new Conjoon_Modules_Groupware_Email_Address($dcc);
            $cc[]       = $add;
            $toString[] = $add->__toString();
        }
        foreach ($data['bcc'] as $dbcc) {
            $add         = new Conjoon_Modules_Groupware_Email_Address($dbcc);
            $bcc[]       = $add;
        }
        foreach ($data['to'] as $dto) {
            $add        = new Conjoon_Modules_Groupware_Email_Address($dto);
            $to[]       = $add;
            $toString[] = $add->__toString();
        }

        $toString  = implode(', ', $toString);
        $ccString  = implode(', ', $ccString);

        $data['cc']  = $cc;
        $data['to']  = $to;
        $data['bcc'] = $bcc;

        // get the specified account for the user
        require_once 'Conjoon/BeanContext/Decorator.php';
        require_once 'Conjoon/Keys.php';

        $accountDecorator = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Email_Account_Model_Account'
        );

        $auth   = Zend_Registry::get(Conjoon_Keys::REGISTRY_AUTH_OBJECT);
        $userId = $auth->getIdentity()->getId();

        $account = $accountDecorator->getAccountAsEntity($data['groupwareEmailAccountsId'], $userId);

        // no account found?
        if (!$account) {
            require_once 'Conjoon/Error.php';
            $error = new Conjoon_Error();
            $error = $error->getDto();;
            $error->title = 'Error while moving email to the outbox folder';
            $error->message = 'Could not find specified account.';
            $error->level = Conjoon_Error::LEVEL_ERROR;
            $this->view->error   = $error;
            $this->view->success = false;
            $this->view->item    = null;
            return;
        }

        $draft = Conjoon_BeanContext_Inspector::create(
                'Conjoon_Modules_Groupware_Email_Draft',
                $data,
                true
        );

        $updateCache = false;

        // check whether we need to apply attachments for a previously saved
        // draft
        if ($draft->getId() > 0) {

            $updateCache = true;

            /**
             * @see Conjoon_Modules_Groupware_Email_Attachment_Filter_AttachmentResponse
             */
            require_once 'Conjoon/Modules/Groupware/Email/Attachment/Filter/AttachmentResponse.php';
            $attDecorator = new Conjoon_BeanContext_Decorator(
                'Conjoon_Modules_Groupware_Email_Attachment_Model_Attachment',
                new Conjoon_Modules_Groupware_Email_Attachment_Filter_AttachmentResponse(
                    array(),
                    Conjoon_Modules_Groupware_Email_Attachment_Filter_AttachmentResponse::CONTEXT_RESPONSE
                )
            );
            $atts = $attDecorator->getAttachmentsForItemAsEntity($draft->getId());
            $draft->setAttachments($atts);
        }

        /**
         * @see Conjoon_BeanContext_Decorator
         */
        require_once 'Conjoon/BeanContext/Decorator.php';

        /**
         * @see Conjoon_Modules_Groupware_Email_Item_Filter_ItemResponse
         */
        require_once 'Conjoon/Modules/Groupware/Email/Item/Filter/ItemResponse.php';

        $itemDecorator = new Conjoon_BeanContext_Decorator(
            'Conjoon_Modules_Groupware_Email_Item_Model_Item',
            new Conjoon_Modules_Groupware_Email_Item_Filter_ItemResponse(
                array(),
                Conjoon_Filter_Input::CONTEXT_RESPONSE
            ),
            false
        );

        $item = $itemDecorator->moveDraftToOutboxAsDto(
            $draft, $account, $userId, $data['type'], $data['referencesId'],
            $postedAttachments, $removeAttachmentIds
        );

        if (!$item) {
            require_once 'Conjoon/Error.php';
            $error = new Conjoon_Error();
            $error = $error->getDto();;
            $error->title = 'Error while saving email';
            $error->message = 'The email could not be stored into the database.';
            $error->level = Conjoon_Error::LEVEL_ERROR;
            $this->view->error   = $error;
            $this->view->success = false;
            $this->view->item    = null;
            return;
        }

        if ($updateCache) {

            /**
             * @see Conjoon_Modules_Groupware_Email_Message_Facade
             */
            require_once 'Conjoon/Modules/Groupware/Email/Message/Facade.php';

            // update cache
            Conjoon_Modules_Groupware_Email_Message_Facade::getInstance()
                ->removeMessageFromCache(
                    $item->id,
                    $this->_helper->registryAccess()->getUserId()
            );
        }

        $this->view->error   = null;
        $this->view->success = true;
        $this->view->item    = $item;
    }

}