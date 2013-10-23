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

namespace Conjoon\Data\Entity\Mail;

/**
 * @see \Conjoon\Data\Entity\Mail\AbstractMessageEntity
 */
require_once 'Conjoon/Data/Entity/Mail/AbstractMessageEntity.php';

/**
 * Default implementation for Message Entity.
 *
 * @category   Conjoon_Data
 * @package    Entity
 *
 * @author Thorsten-Suckow-Homberg <tsuckow@conjoon.org>
 */
class ImapMessageEntity extends AbstractMessageEntity {

    /**
     * @var string
     */
    protected $messageId;

    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getMessageId()
    {
        return $this->messageId;
    }

}
