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
 * @see Intrabuild_Dto
 */
require_once 'Intrabuild/Dto.php';

class Intrabuild_Modules_Groupware_Email_Message_Dto extends Intrabuild_Dto {

    public $id;
    public $to;
    public $cc;
    public $bcc;
    public $replyTo;
    public $from;
    public $subject;
    public $date;
    public $isSpam;
    public $isPlainText;
    public $body;
    public $attachments;
    public $groupwareEmailFoldersId;

}