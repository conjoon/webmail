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

namespace Conjoon\Mail\Client\Message\Strategy;

/**
 * @see Conjoon\Argument\ArgumentCheck
 */
require_once 'Conjoon/Argument/ArgumentCheck.php';

/**
 * @see \Conjoon\Mail\Client\Message\Strategy\PlainReadableStrategy;
 */
require_once 'Conjoon/Mail/Client/Message/Strategy/PlainReadableStrategy.php';

/**
 * @see \Conjoon\Mail\Client\Message\Strategy\ReadableStrategyResult
 */
require_once 'Conjoon/Mail/Client/Message/Strategy/ReadableStrategyResult.php';


use \Conjoon\Argument\ArgumentCheck,
    \Conjoon\Mail\Client\Message\Strategy\PlainReadableStrategy,
    \Conjoon\Mail\Client\Message\Strategy\ReadableStrategyResult;

/**
 * Default implementation for parsing a mail body to plain format along with
 * link formatting, emoticons etc.
 *
 * @package Conjoon
 * @category Conjoon\Mail
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
class DefaultPlainReadableStrategy implements PlainReadableStrategy {

    /**
     * The message text to transform can be found in $data['message']['contentTextPlain'].
     *
     * @inheritdoc
     */
    public function execute(array $data) {

        try {

            ArgumentCheck::check(array(
                'message' => array(
                    'type' => 'array',
                    'allowEmpty' => false
                )), $data);

            ArgumentCheck::check(array(
                'contentTextPlain' => array(
                    'type' => 'string',
                    'allowEmpty' => true
                )), $data['message']);

            $text = $data['message']['contentTextPlain'];

            /**
             * @ticket CN-920
             */
            if (trim($text) == "") {
                return new ReadableStrategyResult("", false, false);
            }

            /**
             * @see \Conjoon_Filter_UrlToATag
             */
            require_once 'Conjoon/Filter/UrlToATag.php';

            /**
             * @see \Conjoon_Filter_QuoteToBlockquote
             */
            require_once 'Conjoon/Filter/QuoteToBlockquote.php';

            /**
             * @see \Conjoon_Filter_SignatureWrap
             */
            require_once 'Conjoon/Filter/SignatureWrap.php';

            /**
             * @see \Conjoon_Filter_NormalizeLineFeeds
             */
            require_once 'Conjoon/Filter/NormalizeLineFeeds.php';

            /**
             * @see \Conjoon_Filter_PlainToHtml
             */
            require_once 'Conjoon/Filter/PlainToHtml.php';

            /**
             * @see \Conjoon_Filter_EmoticonReplacement
             */
            require_once 'Conjoon/Filter/EmoticonReplacement.php';

            $urlFilter = new \Conjoon_Filter_UrlToATag(array(
                'target' => '_blank'
            ));
            $quoteFilter     = new \Conjoon_Filter_QuoteToBlockquote();
            $lineFeedFilter  = new \Conjoon_Filter_NormalizeLineFeeds();
            $signatureFilter = new \Conjoon_Filter_SignatureWrap(
                '<div class="signature">',
                '</div>'
            );
            $plainToHtmlFilter  = new \Conjoon_Filter_PlainToHtml();
            $emoticonFilter     = new \Conjoon_Filter_EmoticonReplacement(
                array(
                    'O:-)'    => '<span class="emoticon innocent"></span>',
                    ':-)'     => '<span class="emoticon smile"></span>',
                    ':)'      => '<span class="emoticon smile"></span>',
                    ':-D'     => '<span class="emoticon laughing"></span>',
                    ':D'      => '<span class="emoticon laughing"></span>',
                    ':-('     => '<span class="emoticon frown"></span>',
                    ':('      => '<span class="emoticon frown"></span>',
                    ':-['     => '<span class="emoticon embarassed"></span>',
                    ';-)'     => '<span class="emoticon wink"></span>',
                    ';)'      => '<span class="emoticon wink"></span>',
                    ':-\\'    => '<span class="emoticon undecided"></span>',
                    ':-P'     => '<span class="emoticon tongue"></span>',
                    ';-P'     => '<span class="emoticon tongue"></span>',
                    ':P'      => '<span class="emoticon tongue"></span>',
                    '=-O'     => '<span class="emoticon surprise"></span>',
                    ':-*'     => '<span class="emoticon kiss"></span>',
                    ':*'      => '<span class="emoticon kiss"></span>',
                    '&gt;:o'  => '<span class="emoticon yell"></span>',
                    '&gt;:-o' => '<span class="emoticon yell"></span>',
                    '8-)'     => '<span class="emoticon cool"></span>',
                    ':-$'     => '<span class="emoticon money"></span>',
                    ':-!'     => '<span class="emoticon foot"></span>',
                    ':\'('    => '<span class="emoticon cry"></span>',
                    ':-X'     => '<span class="emoticon sealed"></span>'
                ));

            /**
             * @see \Conjoon_Text_Transformer_Mail_EmailAddressToHtmlTransformer
             */
            require_once 'Conjoon/Text/Transformer/Mail/EmailAddressToHtmlTransformer.php';

            $transformer = new \Conjoon_Text_Transformer_Mail_EmailAddressToHtmlTransformer();

            /**
             * @see \Conjoon\Text\Transformer\Html\SanitizeOpeningBracketForLinkTransformer
             */
            require_once 'Conjoon/Text/Transformer/Html/SanitizeOpeningBracketForLinkTransformer.php';

            $openingBracketForLinkTransformer =
                new \Conjoon\Text\Transformer\Html\SanitizeOpeningBracketForLinkTransformer();


            /**
             * @see \Zend_Filter_HtmlEntities
             */
            require_once 'Zend/Filter/HtmlEntities.php';

            $zfe  = new \Zend_Filter_HtmlEntities(
                array(
                    'quotestyle' => ENT_COMPAT/*,
                    'charset'    => 'UTF-8'*/
                )
            );



            return new ReadableStrategyResult(
                $openingBracketForLinkTransformer->transform(
                    $transformer->transform(
                        $plainToHtmlFilter->filter(
                            $signatureFilter->filter(
                                $quoteFilter->filter(
                                    $urlFilter->filter(
                                        $emoticonFilter->filter(
                                            $lineFeedFilter->filter(
                                                $zfe->filter($text)
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
            ), false, false);

        } catch (\Exception $e) {

            /**
             * @see \Conjoon\Mail\Client\Message\Strategy\StrategyException;
             */
            require_once 'Conjoon/Mail/Client/Message/Strategy/StrategyException.php';

            throw new StrategyException(
                "Exception thrown by previous exception", 0, $e
            );

        }

    }


}
