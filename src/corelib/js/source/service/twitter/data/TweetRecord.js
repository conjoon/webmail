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

Ext.namespace('com.conjoon.service.twitter.data');

/**
 * This class models a "tweet", i.e. a status update message for
 * the Twitter service. Along with the message's relevant data, a record
 * also contains details about the Twitter user who posted the "tweet".
 *
 * Unless stated otherwise, all values for this record are managed by the Twitter
 * service.
 *
 * @class com.conjoon.service.twitter.data.TweetRecord
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */
com.conjoon.service.twitter.data.TweetRecord = Ext.data.Record.create([

    /**
     * @type {Number} id The id of the tweet, as managed by the Twitter service.
     * This should not be converted to an integer, see http://www.twitpocalypse.com/
     */
    {name : 'id',              type : 'string'},

    /**
     * @type {String} text The actual text of the status update.
     */
    {name : 'text',            type : 'string'},

    /**
     * @type {String} createdAt The date the status update was created, as managed
     * by the Twitter service.
     */
    {name : 'createdAt', sortType : Ext.data.SortTypes.asDate, type : 'date',  dateFormat : 'Y-m-d H:i:s'},

    /**
     * @type {String} sourceName Stripped from the source-property from the Twitter
     * service response, this property holds the application's name this tweet was
     * posted from. Twitter may have approved the source id and provide the URL to the
     * application's project home in the "sourceUrl" property.
     */
    {name : 'source',          type : 'string'},

    /**
     * @type {String} sourceUrl Stripped from the source-property from the Twitter
     * service response, this property holds the url to the application's project home
     * this tweet was posted from. May be null if the source did not belong to an approved
     * source id by the Twitter service.
     */
    {name : 'sourceUrl',          type : 'string'},

    /**
     * @type {Boolean} truncated Whether this update was truncated by the Twitter
     * service.
     */
    {name : 'truncated',       type : 'bool'},

    /**
     * @type {Number} userId The id of the user who posted this tweet, as managed by
     * the Twitter service.
     */
    {name : 'userId',          type : 'string'},

    /**
     * @type {String} name The name of the user who posted this tweet as managed by the
     * Twitter service. Note: this property holds the real name of the user. The name
     * used to identify the account can be found in "screenName".
     */
    {name : 'name',            type : 'string'},

    /**
     * @type {String} screenName The name used to identify a Twitter account.
     */
    {name : 'screenName',      type : 'string'},

    /**
     * @type {String} location The location of the user who posted this tweet as managed by
     * the twitter service.
     */
    {name : 'location',        type : 'string'},

    /**
     * @type {String} profileImageUrl The url to the user's image who posted this tweet, as
     * managed by the twitter service.
     */
    {name : 'profileImageUrl', type : 'string'},

    /**
     * @type {String} url The url of the user who posted this tweet, as managed by the Twitter
     * service.
     */
    {name : 'url',             type : 'string'},

    /**
     * @type {Boolean} protected Whether status updates of this user are protected, as managed
     * by the Twitter service.
     */
    {name : 'protected',       type : 'bool'},

    /**
     * @type {String} description The description/bio of the user who posted this tweet, as
     * managed by the twitter service.
     */
    {name : 'description',     type : 'string'},

    /**
     * @type {Number} followersCount The number of followers for the user who posted this
     * tweet, as managed by the Twitter service.
     */
    {name : 'followersCount',  type : 'int'},

    /**
     * @type {Boolean} isFollowing Whether the current user follows the user that created
     * this tweet.
     */
    {name : 'isFollowing',  type : 'bool'},

    /**
     * @type {Number} inReplyToStatusId The id of the tweet this tweet referrs to, as managed
     * by the Twitter service. May be null. This should not be converted to an integer. See
     * http://www.twitpocalypse.com/
     */
    {name : 'inReplyToStatusId',  type : 'string'},

    /**
     * @type {Number} inReplyToUserId The id of the user of the tweet this tweet
     * referrs to, as managed by the Twitter service. May be null.
     */
    {name : 'inReplyToUserId',  type : 'string'},

    /**
     * @type {String} inReplyToScreenName The screen name of the user of the tweet
     * this tweet referrs to, as managed by the Twitter service. May be null.
     */
    {name : 'inReplyToScreenName',  type : 'string'},

    /**
     * @type {Number} favorited Whether this tweet was favorited by the user which is
     * viewing this tweet, as managed by the Twitter service. May be null.
     */
    {name : 'favorited',  type : 'bool'}

]);
