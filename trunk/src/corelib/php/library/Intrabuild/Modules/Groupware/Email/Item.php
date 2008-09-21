<?php
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
 * Intrabuild_BeanContext
 */
require_once 'Intrabuild/BeanContext.php';


/**
 * An email item defines itself as a collection of data from the emails header,
 * such as
 *  to
 *  cc
 *  from
 *  subject
 *  date (delivery date)
 *
 * Additionally, a few other properties will be set, which will help to identify
 * the properties of the email represented by the item:
 *
 * isAttachment -> wether the email item has attachments or not
 * isRead -> wether or not the email was read by the current user viewing the email
 * isSpam -> wether or not the email was marked as spam by the current user
 * isDraft -> wether or not the email item is a draft created by a user, i.e. an email
 * that is being written and will be send later on
 *
 * @uses       Intrabuild_BeanContext
 * @category   Intrabuild_Groupware
 * @package    Intrabuild_Groupware
 * @subpackage Email
 *
 * @author Thorsten-Suckow-Homberg <ts@siteartwork.de>
 */

class Intrabuild_Modules_Groupware_Email_Item implements Intrabuild_BeanContext, Serializable {

    private $id;
    private $to;
    private $cc;
    private $from;
    private $subject;
    private $date;
    private $isRead;
    private $isAttachment;
    private $isSpam;
    private $isDraft;
    private $groupwareEmailFoldersId;

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

// -------- accessors

    public function getId(){return $this->id;}
    public function getTo(){return $this->to;}
    public function getCc(){return $this->cc;}
    public function getFrom(){return $this->from;}
    public function getSubject(){return $this->subject;}
    public function getDate(){return $this->date;}
    public function isRead(){return $this->isRead;}
    public function isAttachment(){return $this->isAttachment;}
    public function isSpam(){return $this->isSpam;}
    public function isDraft(){return $this->isDraft;}
    public function getGroupwareEmailFoldersId(){return $this->groupwareEmailFoldersId;}

    public function setId($id){$this->id = $id;}
    public function setTo($to){$this->to = $to;}
    public function setCc($cc){$this->cc = $cc;}
    public function setFrom($from){$this->from = $from;}
    public function setSubject($subject){$this->subject = $subject;}
    public function setDate($date){$this->date = $date;}
    public function setRead($isRead){$this->isRead = $isRead;}
    public function setAttachment($isAttachment){$this->isAttachment = $isAttachment;}
    public function setSpam($isSpam){$this->isSpam = $isSpam;}
    public function setDraft($isDraft){$this->isDraft = $isDraft;}
    public function setGroupwareEmailFoldersId($groupwareEmailFoldersId){$this->groupwareEmailFoldersId = $groupwareEmailFoldersId;}


// -------- interface Serializable
    /**
     * Serializes properties and returns them as a string which can later on
     * be unserialized.
     *
     * @return string
     */
    public function serialize()
    {
        $data = $this->toArray();

        return serialize($data);
    }

    /**
     * Unserializes <tt>$serialized</tt> and assigns the specific
     * values found to the members in this class.
     *
     * @param string $serialized The serialized representation of a former
     * instance of this class.
     */
    public function unserialize($serialized)
    {
        $str = unserialize($serialized);

  	     foreach ($str as $member => $value) {
            $this->$member = $value;
        }
    }

// -------- interface Intrabuild_BeanContext

    /**
     * Returns a Dto for an instance of this class.
     *
     * @return Intrabuild_Groupware_Email_AccountDto
     */
    public function getDto()
    {
        require_once 'Item/Dto.php';

        $data = $this->toArray();

        $dto = new Intrabuild_Modules_Groupware_Email_Item_Dto();
        foreach ($data as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->$key = $value;
            }
        }

        return $dto;
    }

    /**
     * Returns an associative array, which key/value pairs represent
     * the properties stored by this object.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'id'           => $this->id,
            'to'           => $this->to,
            'cc'           => $this->cc,
            'from'         => $this->from,
            'subject'      => $this->subject,
            'date'         => $this->date,
            'isRead'       => $this->isRead,
            'isAttachment' => $this->isAttachment,
            'isSpam'       => $this->isSpam,
            'isDraft'      => $this->isDraft,
            'groupwareEmailFoldersId' => $this->groupwareEmailFoldersId
        );
    }

    /**
     * Returns a textual representation of the current object.
     *
     * @return string
     */
    public function __toString()
    {
        $data = $this->toArray();

        $strs = array();
        foreach ($data as $key => $value) {
            $strs[] = $key.': '.$value;
        }
        return get_class($this).'['.implode('; ', $strs).']';
    }
}