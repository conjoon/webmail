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

namespace Conjoon\Mail\Client\Security;

use Conjoon\Argument\ArgumentCheck,
    Conjoon\Argument\InvalidArgumentException;

/**
 * @see Conjoon\Argument\ArgumentCheck
 */
require_once 'Conjoon/Argument/ArgumentCheck.php';

/**
 * @see Conjoon\Mail\Client\Security\SecurityServiceException
 */
require_once 'Conjoon/Mail/Client/Security/SecurityServiceException.php';



/**
 * @category   Conjoon_Mail
 * @package    Folder
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
class DefaultAccountSecurityService implements AccountSecurityService {

    /**
     * @var \Conjoon\User\User
     */
    protected $user;

    /**
     * @var \Conjoon\Mail\Client\Account\AccountBasicService
     */
    protected $accountBasicService;


    /**
     * Creates a new instance of this security service.
     *
     * @param Array $options an array of options with the following
     *              key/value-pairs:
     *              - user: The user bound to this service. An instance of
     *                \Conjoon\User\User
     *              - accountBasicService: The
     *                \Conjoon\Mail\Client\Account\AccountBasicService
     *                this service uses.
     *
     * @throws \Conjoon\Argument\InvalidArgumentException
     */
    public function __construct(Array $options)
    {
        $data = array('options' => $options);

        ArgumentCheck::check(array(
            'options' => array(
                'type'       => 'array',
                'allowEmpty' => false
            )
        ), $data);

        ArgumentCheck::check(array(
            'user' => array(
                'type'  => 'instanceof',
                'class' => 'Conjoon\User\User'
            ),
            'accountBasicService' => array(
                'type'  => 'instanceof',
                'class' => 'Conjoon\Mail\Client\Account\AccountBasicService'
            ),
        ), $options);

        $this->accountBasicService = $options['accountBasicService'];
        $this->user                = $options['user'];
    }

    /**
     * @inheritdoc
     */
    public function isAccountAccessible(
        \Conjoon\Mail\Client\Account\Account $account){

        $accountEntity = null;

        try {
            $accountEntity = $this->accountBasicService->getAccountEntity($account);
        } catch (\Conjoon\Mail\Client\Account\AccountServiceException $e) {
            throw new AccountSecurityServiceException(
                "Exception thrown by previous exception: " .
                $e->getMessage(), 0, $e
            );
        }

        if ($this->user->equals($accountEntity->getUser())) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getUser() {
        return $this->user;
    }

}