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
 * @see Conjoon_Filter_Raw
 */
require_once 'Conjoon/Filter/Raw.php';

/**
 * An input-filter class defining all validators and filters needed when
 * processing input data for mutating or creating Email-Accounts.
 *
 * @uses Conjoon_Filter_Input
 * @package    Conjoon_Filter_Input
 * @category   Filter
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
class Conjoon_Modules_Default_Registry_Filter_Registry extends Conjoon_Filter_Input {

    /**
     * @const string CONTEXT_UPDATE_REQUEST
     */
    const CONTEXT_UPDATE_REQUEST = 'update_request';

    protected $_presence = array(
        'update_request' => array(
            'data'
        ),
        self::CONTEXT_UPDATE => array(
            'key',
            'value'
        )
    );

    protected $_filters = array(
        'data' => array(
            array('ExtDirectWriterFilter')
            // additional filters actually set in _init depending on the context
         ),
         'key'   => array('StringTrim'),
         'value' => array('Raw')
    );

    protected $_validators = array(
         'data' => array(
            'allowEmpty' => false
         ),
         'key' => array(
            'allowEmpty' => false
         ),
         'value' => array(
            'allowEmpty' => true
         )
    );

    protected $_dontRecurseFilter = array(
        'data'
    );

    protected function _init()
    {
        $this->_defaultEscapeFilter = new Conjoon_Filter_Raw();
    }
}