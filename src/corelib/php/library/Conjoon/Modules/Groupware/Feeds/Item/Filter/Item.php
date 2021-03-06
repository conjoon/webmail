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
 * @see Conjoon_Filter_Input
 */
require_once 'Conjoon/Filter/Input.php';

/**
 * @see Conjoon_Filter_FormBoolToInt
 */
require_once 'Conjoon/Filter/FormBoolToInt.php';

/**
 * @see Conjoon_Filter_Raw
 */
require_once 'Conjoon/Filter/Raw.php';

/**
 * @see Conjoon_Filter_ShortenString
 */
require_once 'Conjoon/Filter/ShortenString.php';

/**
 * @see Conjoon_Filter_HtmlEntityDecode
 */
require_once 'Conjoon/Filter/HtmlEntityDecode.php';

/**
 * An input-filter class defining all validators and filters needed when
 * processing input data for mutating or creating feed items.
 *
 * @uses Conjoon_Filter_Input
 * @package    Conjoon_Filter_Input
 * @category   Filter
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
class Conjoon_Modules_Groupware_Feeds_Item_Filter_Item extends Conjoon_Filter_Input {

    const CONTEXT_READ = 'flag_item';

    const CONTEXT_ITEM_CONTENT = 'item_content';

    const CONTEXT_URI_CHECK = 'uri_check';

    const CONTEXT_ITEM_RESPONSE = 'item_response';

    const CONTEXT_ITEM_RESPONSE_IMG = 'item_response_img';

    protected $_presence = array(
        self::CONTEXT_ITEM_CONTENT => array(
            'id',
            'groupwareFeedsAccountsId'
        ),
        self::CONTEXT_URI_CHECK => array(
            'uri'
        ),
        'delete' =>
            array(
                'id'
            )
        ,
        'flag_item' =>
            array(
                'read',
                'unread'
            ),
        'update' =>
            array(
                'removeold',
                'timeout'
            ),
        self::CONTEXT_RESPONSE => array(
            'id',
            'groupwareFeedsAccountsId',
            'name',
            'title',
            'author',
            'authorUri',
            'authorEmail',
            'description',
            'pubDate',
            'link',
            'isRead'
        ),
        self::CONTEXT_ITEM_RESPONSE => array(
            'id',
            'groupwareFeedsAccountsId',
            'name',
            'content',
            'title',
            'author',
            'authorUri',
            'authorEmail',
            'description',
            'pubDate',
            'link',
            'isRead'
        ),
        self::CONTEXT_ITEM_RESPONSE_IMG => array(
            'id',
            'groupwareFeedsAccountsId',
            'name',
            'content',
            'title',
            'author',
            'authorUri',
            'authorEmail',
            'description',
            'pubDate',
            'link',
            'isRead'
        ),
        'create' =>
            array(
                'title',
                'description',
                'content',
                'pubDate',
                'link',
                'guid',
                'author',
                'authorUri',
                'authorEmail',
                'savedTimestamp',
                'groupwareFeedsAccountsId'
        )
    );

    protected $_filters = array(
        'uri'  => array(
            'StringTrim'
        ),
        'read' => array(
            'JsonDecode',
            'PositiveArrayValues'
        ),
        'unread' => array(
            'JsonDecode',
            'PositiveArrayValues'
        ),
        'id' => array(
            'Int'
         ),
         'name' => array(
            'StringTrim'
         ),
         'groupwareFeedsAccountsId' => array(
            'Int'
         ),
        'title' => array(
            'StringTrim'
         ),
         'guid' => array(
            'StringTrim'
         ),
         'author' => array(
            'StringTrim'
         ),
         'authorUri' => array(
            'StringTrim'
         ),
         'authorEmail' => array(
            'StringTrim'
         ),
        'description' => array(
            'StringTrim'
         ),
         'content' => array(
            'StringTrim'
         ),
        'pubDate' => array(
            'StringTrim'
         ),
        'link' => array(
            'StringTrim'
        ),
        'savedTimestamp' => array(
            'Int'
        ),
        'isRead' => array(
            'Int'
        ),
        'removeold' => array(),
        'timeout'   => 'Int',
    );

    protected $_validators = array(
        'uri' => array(
            'allowEmpty' => false
         ),
        'id' => array(
            'allowEmpty' => false,
            array('GreaterThan', 0)
         ),
         'groupwareFeedsAccountsId' => array(
            'allowEmpty' => false,
            array('GreaterThan', 0)
         ),
        'title' => array(
            'allowEmpty' => true
         ),
         'name' => array(
            'allowEmpty' => true,
            'default' => ''
         ),
        'author' => array(
            'allowEmpty' => true,
            'default' => ''
         ),
         'authorUri' => array(
            'allowEmpty' => true,
            'default' => ''
         ),
         'authorEmail' => array(
            'allowEmpty' => true,
            'default' => ''
         ),
        'description' => array(
            'allowEmpty' => true,
            'default' => ''
         ),
        'pubDate' => array(
            'allowEmpty' => false
         ),
         'content' => array(
            'allowEmpty' => true,
            'default' => ''
         ),
         'guid' => array(
            'allowEmpty' => false
         ),
        'link' => array(
            'allowEmpty' => false
        ),
        'savedTimestamp' => array(
            'presence' => 'optional',
            'allowEmpty' => true,
            array('GreaterThan', 0)
        ),
        'isRead' => array(
           'allowEmpty' => true,
           'default'    => 0
        ),
        'removeold' => array(
            'allowEmpty' => true,
            'default'    => 0
        ),
        'timeout' => array(
            'allowEmpty' => true,
            array('GreaterThan', 0),
            'default'    => 30000
        )
    );

    protected function _init()
    {
        if ($this->_context == self::CONTEXT_RESPONSE
           || $this->_context == self::CONTEXT_ITEM_RESPONSE
           || $this->_context == self::CONTEXT_ITEM_RESPONSE_IMG) {

            $this->_filters['pubDate'] = array(
                'StringTrim',
                'DateUtcToLocal'
            );

            $this->_filters['title'] = array(
                array('MyHtmlEntities'),
                array('StripTags')
            );

            $this->_filters['description'] = array(
                array('MyHtmlEntities'),
                array('StripTags',
                    // allow all except img, a, object, script, embed etc.
                    array(
                        'p','div','span','ul','li','table','tr','td','blockquote',
                        'strong','b','i','u','sup','sub','tt','h1','h2','h3','h4',
                        'h5','h6','small','big','br','nobr','center','ol', 'font',
                        'pre'
                    ),
                    array(
                        'style', 'class','id', 'href', 'border', 'cellspacing', 'cellpadding'
                    )
                ),
                array(
                    'PregReplace',
                    '/href="javascript:/',
                    'href="/index/javascript/'
                ),
                array(
                    'PregReplace',
                    "/href='javascript:/",
                    "href='/index/javascript/"
                ),
                'StringTrim',
                new Conjoon_Filter_ShortenString(128, '...')
            );

            if ($this->_context == self::CONTEXT_ITEM_RESPONSE
               || $this->_context == self::CONTEXT_ITEM_RESPONSE_IMG) {

                $allowedTags = array(
                    'p','div','span','ul','li','table','tr','td','blockquote',
                    'strong','b','i','u','sup','sub','tt', 'tbody', 'h1','h2','h3','h4',
                    'h5','h6','small','big','br','nobr','center','ol','a', 'link','font',
                    'pre'
                );

                $allowedAttributes = array(
                    'style', 'class','id', 'href', 'border', 'cellspacing',
                    'cellpadding', 'valign', 'rowspan', 'colspan', 'alt', 'border'
                );

                if ($this->_context == self::CONTEXT_ITEM_RESPONSE_IMG) {
                    $allowedTags[]       = 'img';
                    $allowedAttributes[] = 'src';
                }

                $this->_filters['content'] = array(
                    array('MyHtmlEntities'),
                    array('StripTags',
                        // allow all except img, object, script, embed etc.
                        $allowedTags,
                        $allowedAttributes
                    ),
                    array(
                        'PregReplace',
                        '/href="javascript:/',
                        'href="/index/javascript/'
                    ),
                    array(
                        'PregReplace',
                        "/href='javascript:/",
                        "href='/index/javascript/"
                    ),
                    array(
                        'UrlToATag'
                    ),
                    'StringTrim'
                );
            }
        }

        $this->_defaultEscapeFilter = new Conjoon_Filter_Raw();


        $this->_filters['removeold'] = new Conjoon_Filter_FormBoolToInt();
        $this->_validators['savedTimestamp']['default'] = time();
    }

    public function getProcessedData()
    {
        $data = parent::getProcessedData();

        if ($this->_context == self::CONTEXT_RESPONSE
            || $this->_context == self::CONTEXT_ITEM_RESPONSE
            || $this->_context == self::CONTEXT_ITEM_RESPONSE_IMG) {

            /**
             * @see Conjoon_Filter_ExtractHost
             */
            require_once 'Conjoon/Filter/ExtractHost.php';

            $hostFilter = new Conjoon_Filter_ExtractHost();

            $host = $hostFilter->filter($data['link']);

            /**
             * @see Conjoon_Filter_SanitizeRelativeUrls
             */
            require_once 'Conjoon/Filter/SanitizeRelativeUrls.php';

            $sanitizeUrls = new Conjoon_Filter_SanitizeRelativeUrls(
                $host,
                array('/index/javascript')
            );

            $data['description'] = $sanitizeUrls->filter($data['description']);

            if ($this->_context != self::CONTEXT_RESPONSE) {
                $data['content'] = $sanitizeUrls->filter($data['content']);
            }

        }


        return $data;

    }

}