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
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';


/**
 * @category   Filter
 * @package    Conjoon_Filter
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
class Conjoon_Filter_StringPrependIf implements Zend_Filter_Interface
{
    private $_startsWith = array();

    private $_prependWith = "";

    /**
     * Constructor.
     *
     * @param array $startsWith An array with strings to check if they occur
     * at the start of the string to filter.
     * @param string $prependWith The string to prepend the filtered string with
     * if none of the strings in $startsWith where found
     *
     */
    public function __construct($startsWith = array(), $prependWith = "")
    {
        $this->_startsWith  = $startsWith;
        $this->_prependWith = $prependWith;
    }

    /**
     * Defined by Zend_Filter_Interface
     *
     * Prepends the string with the given value if and only if it does not start with any
     * strings found in $startsWith.
     *
     * @param  mixed $value
     * @return string
     */
    public function filter($value)
    {
        $orgValue = $value;

        if ($this->_prependWith === "") {
            return $orgValue;
        }

        $value = ltrim((string)$value);

        // special case: trimmed strings are equal
        if (trim($orgValue) == trim($this->_prependWith)) {
            return $orgValue;
        }

        if (empty($this->_startsWith)) {
            return $this->_prependWith . $value;
        }

        $found = false;
        foreach ($this->_startsWith as $sub) {
            if (strpos($value, $sub) === 0) {
                $found = true;
                break;
            }
        }

        if ($found) {
            return $orgValue;
        }

        return $this->_prependWith . $value;
    }
}