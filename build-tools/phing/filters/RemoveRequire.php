<?php
/**
 * conjoon
 * (c) 2007-2014 conjoon.org
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
 * @see BaseFilterReader
 */
require_once 'phing/filters/BaseFilterReader.php';

/**
 * @see ChainableReader
 */
require_once 'phing/filters/ChainableReader.php';

/**
 * Processes development files the following way:
 *
 * Processes development files and removes all require_once statements in favor
 * of lazy loading class files only when needed.
 * Additionally, any @see doc blocks preceeding the require_once statements will
 * be removed, too.
 *
 * @author    Thorsten Suckow-Homberg <tsuckow@conjoon.org>
*  @author    Marc Steinert <marc@bithub.net>
 * @see       FilterReader
 */
class RemoveRequire extends BaseFilterReader implements ChainableReader {

    private $processed = false;

    /**
     * Returns the stream without require_once statements.
     *
     * @return the resulting stream, or -1
     *         if the end of the resulting stream has been reached
     *
     * @throws IOException if the underlying stream throws an IOException
     *                        during reading
     */
    function read($len = null) {

        if ($this->processed === true) {
            return -1; // EOF
        }

        // Read
        $php = null;
        while ( ($buffer = $this->in->read($len)) !== -1 ) {
            $php .= $buffer;
        }

        if ($php === null ) { // EOF?
            return -1;
        }

        if(empty($php)) {
            $this->log("File is empty!", Project::MSG_WARN);
            return ''; // return empty string
        }

        // write buffer to a temporary file, since php_strip_whitespace() needs a filename
        $file = new PhingFile(tempnam(PhingFile::getTempDir(), 'stripwhitespace'));
        file_put_contents($file->getAbsolutePath(), $php);

        $output = $php;

        $matches = array();

        if (preg_match_all(
            '|/\*\*(.*?)\*/\s+(require_once \'.*?\';\s*)?|s',
            $output,
            $matches,
            PREG_SET_ORDER
        )) {
           foreach ($matches as $match) {
               list($all, $docblock, $require) = $match;
               if (strstr($docblock, '@see')) {
                   // Remove docblock and require_once, if it contains @see
                   $output = str_replace($all, '', $output);
                } else {
                    // Just remove require_once statement
                    $output = str_replace($require, '', $output);
                }
            }
        }

        $output = preg_replace("/(^\s*require_once.*?;\s*$\s)/sm", "", $output);

        unlink($file->getAbsolutePath());

        $this->processed = true;

        return $output;
    }

    /**
     * Creates a new RemoveRequire using the passed in
     * Reader for instantiation.
     *
     * @param reader A Reader object providing the underlying stream.
     *               Must not be <code>null</code>.
     *
     * @return a new filter based on this configuration, but filtering
     *         the specified reader
     */
    public function chain(Reader $reader) {
        $newFilter = new RemoveRequire($reader);
        $newFilter->setProject($this->getProject());
        return $newFilter;
    }
}
