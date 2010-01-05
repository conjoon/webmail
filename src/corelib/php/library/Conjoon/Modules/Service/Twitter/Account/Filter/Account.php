<?php
/**
 * conjoon
 * (c) 2002-2010 siteartwork.de/conjoon.org
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
 * @author Thorsten Suckow-Homberg <ts@siteartwork.de>
 */
class Conjoon_Modules_Service_Twitter_Account_Filter_Account extends Conjoon_Filter_Input {

    /**
     * @const string CONTEXT_UPDATE_REQUEST
     */
    const CONTEXT_UPDATE_REQUEST = 'update_request';

    protected $_presence = array(
        'create' => array(
            'name',
            'password',
            'updateInterval'
        ),
        'delete' => array(
            'data'
        ),
        'update_request' => array(
            'data'
        ),
        'update' => array(
            'name',
            'password',
            'updateInterval',
            'id'
        )
    );

    protected $_filters = array(
        'name' => array(
            'StringTrim'
         ),
        'password' => array(
            'StringTrim'
         ),
         'updateInterval' => array(
            'Int'
         ),
         'data' => array(
            array('ExtDirectWriterFilter', 'accounts')
            // additional filters actually set in _init depending on the context
         ),
         'id' => array(
            'Int'
         )
    );

    protected $_validators = array(
        'name' => array(
            'allowEmpty' => false
         ),
        'password' => array(
            'allowEmpty' => false
         ),
        'updateInterval' => array(
            'allowEmpty' => true,
            'default'    => 60000
         ),
         'id' => array(
            'allowEmpty' => false,
            array('GreaterThan', 0)
         ),
         'data' => array(
            'allowEmpty' => false
         )
    );

    protected $_dontRecurseFilter = array(
        'data'
    );

    protected function _init()
    {
        $this->_defaultEscapeFilter = new Conjoon_Filter_Raw();

        if ($this->_context == self::CONTEXT_DELETE) {
            $this->_filters['data'][] = 'PositiveArrayValues';
        } else if ($this->_context == self::CONTEXT_UPDATE) {
            $this->_validators['name']['presence']           = 'optional';
            $this->_validators['password']['presence']       = 'optional';

            $this->_validators['updateInterval'] = array(
                'allowEmpty' => true,
                'presence'   => 'optional'
            );
        }
    }

    public function getProcessedData()
    {
        $data = parent::getProcessedData();

        if ($this->_context == self::CONTEXT_UPDATE) {
            if ($data['updateInterval'] === NULL) {
                unset($data['updateInterval']);
            }

            if (isset($data['updateInterval'])) {
                $v = $data['updateInterval'];
                $data['update_interval'] = $v;
            }

            if ($data['name'] === NULL) {
                unset($data['name']);
            }
            if ($data['password'] === NULL
                || str_replace('*', '', $data['password']) == "") {
                unset($data['password']);
            }
        }

        return $data;
    }

}