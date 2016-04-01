<?php

ini_set('date.timezone', 'Asia/Tokyo');

require_once './twitteroauth-master/twitteroauth/twitteroauth.php';

/**
 * @link https://dev.twitter.com/rest/public
 * @link https://github.com/abraham/twitteroauth
 * @link https://apps.twitter.com/
 */
class TwitterApi
{
    private static $twitter = array();
    private        $apiKey  = null;

    const SLEEP_TIME             = 100000; // APIを叩くときにスリープ時間を設ける(0.1秒)
    const DEFAULT_TWEET_LANGUAGE = 'ja';   // 言語初期値

    public function __construct(array $account)
    {
        self::_setTwitterOAuth($account);
    }

    /**
     * ツイートする
     *
     * @param  string $status             : ツイート文言
     * @param  int    $inReplyToStatusId  : id を指定 ※ ツイート文言に id のツイートをしたユーザー名(@username)を記述する必要がある
     * @param  bool   $possiblySensitive  : 過激な内容が含まれるツイートの場合は true を入れる
     * @param  float  $lat                : 緯度
     * @param  float  $long               : 経度
     * @param  string $placeId            : ツイートした場所を指定
     * @param  bool   $displayCoordinates : true => 緯度経度を表示する
     * @param  bool   $trimUser           : true => ユーザー情報が縮小する
     * @param  array  $mediaIds           : media_id を付与することで画像などを関連付けることが出来る
     * @return array                      : ツイート情報
     */
    public function setTweet($status, $inReplyToStatusId = null, $possiblySensitive = null, $lat = null, $long = null, $placeId = null, $displayCoordinates = null, $trimUser = null, array $mediaIds = array())
    {
        $param = array();
        if($status             !== null) $param['status']                = $status;
        if($inReplyToStatusId  !== null) $param['in_reply_to_status_id'] = $inReplyToStatusId;
        if($possiblySensitive  !== null) $param['possibly_sensitive']    = $possiblySensitive;
        if($lat                !== null) $param['lat']                   = $lat;
        if($long               !== null) $param['long']                  = $long;
        if($placeId            !== null) $param['place_id']              = $placeId;
        if($displayCoordinates !== null) $param['display_coordinates']   = $displayCoordinates;
        if($trimUser           !== null) $param['trim_user']             = $trimUser;
        if(count($mediaIds)    !== 0)    $param['media_ids']             = implode(',', $mediaIds);

        return self::_getResponseAndCheck('statuses/update.json', 'POST', $param);
    }

    /**
     * リツイートする
     *
     * @param  int   $id       : リツイートしたい id
     * @param  bool  $trimUser : true => ユーザー情報が縮小する
     * @return array           : ツイート情報
     */
    public function setReTweet($id, $trimUser = null)
    {
        $param = array();
        if($trimUser !== null) $param['trim_user'] = $trimUser;

        return self::_getResponseAndCheck("statuses/retweet/{$id}.json", 'POST', $param);
    }

    /**
     * お気に入りに登録
     *
     * @param  int   $id              : お気に入りしたい id
     * @param  bool  $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                  : ツイート情報
     */
    public function setFavorite($id, $includeEntities = null)
    {
        $param = array('id' => $id);
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;

        return self::_getResponseAndCheck('favorites/create.json', 'POST', $param);
    }

    /**
     * ダイレクトメッセージ送信
     *
     * @param  int    $userId     : user_id
     * @param  string $screenName : ユーザー名
     * @param  string $text       : 送信内容
     * @return array              : ダイレクトメッセージ情報
     */
    public function setDirectMessage($userId = null, $screenName = null, $text)
    {
        $param = array('text' => $text);
        if($userId     !== null) $param['user_id']     = $userId;
        if($screenName !== null) $param['screen_name'] = $screenName;

        return self::_getResponseAndCheck('direct_messages/new.json', 'POST', $param);
    }

    /**
     * フォローする
     *
     * @param  int    $userId     : user_id
     * @param  string $screenName : ユーザー名
     * @return array              : ユーザー情報
     */
    public function setFollow($userId = null, $screenName = null)
    {
        $param = array('follow' => 'true');
        if($userId     !== null) $param['user_id']     = $userId;
        if($screenName !== null) $param['screen_name'] = $screenName;

        return self::_getResponseAndCheck('friendships/create.json', 'POST', $param);
    }

    /**
     * ブロックリスト追加
     *
     * @param  int    $userId          : user_id
     * @param  string $screenName      : ユーザー名
     * @param  bool   $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @param  bool   $skipStatus      : true => [status]削除
     * @return array                   : ユーザー情報
     */
    public function setBlockList($userId = null, $screenName = null, $includeEntities = null, $skipStatus = null)
    {
        $param = array();
        if($userId          !== null) $param['user_id']          = $userId;
        if($screenName      !== null) $param['screen_name']      = $screenName;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;
        if($skipStatus      !== null) $param['skip_status']      = $skipStatus;

        return self::_getResponseAndCheck('blocks/create.json', 'POST', $param);
    }

    /**
     * ミュートリスト追加
     *
     * @param  int    $userId     : user_id
     * @param  string $screenName : ユーザー名
     * @return array              : ユーザー情報
     */
    public function setMutes($userId = null, $screenName = null)
    {
        $param = array();
        if($userId     !== null) $param['user_id']     = $userId;
        if($screenName !== null) $param['screen_name'] = $screenName;

        return self::_getResponseAndCheck('mutes/users/create.json', 'POST', $param);
    }

    /**
     * ツイート取得
     *
     * @param  int   $id               : id
     * @param  bool  $trimUser         : true => ユーザー情報が縮小する
     * @param  bool  $includeMyRetweet : true => リイートしたツイートをレスポンスに含める
     * @param  bool  $includeEntities  : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                   : ツイート情報
     */
    public function getTweet($id, $trimUser = null, $includeMyRetweet = null, $includeEntities = null)
    {
        $param = array();
        if($id               !== null) $param['id']                 = $id;
        if($trimUser         !== null) $param['trim_user']          = $trimUser;
        if($includeMyRetweet !== null) $param['include_my_retweet'] = $includeMyRetweet;
        if($includeEntities  !== null) $param['include_entities']   = $includeEntities;

        return self::_getResponseAndCheck('statuses/show.json', 'GET', $param);
    }

    /**
     * 自分のホーム画面のツイートを取得
     * - 最新の200個までしか取得できない
     *
     * @param  int   $count              : 取得したい数
     * @param  int   $sinceId            : 指定したID以前の結果を取得
     * @param  int   $maxId              : 指定したID以降の結果を取得
     * @param  bool  $trimUser           : true => ユーザー情報を縮小する
     * @param  bool  $excludeReplies     : true => リプライツイートも取得
     * @param  bool  $contributorDetails : true => ツイートに screen_name が含まれる場合、詳細なデータが取得できる
     * @param  bool  $includeEntities    : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                     : ツイート情報
     */
    public function getHomeTweets($count = null, $sinceId = null, $maxId = null, $trimUser = null, $excludeReplies = null, $contributorDetails = null, $includeEntities = null)
    {
        $param = array();
        if($count              !== null) $param['count']               = $count;
        if($sinceId            !== null) $param['since_id']            = $sinceId;
        if($maxId              !== null) $param['max_id']              = $maxId;
        if($trimUser           !== null) $param['trim_user']           = $trimUser;
        if($excludeReplies     !== null) $param['exclude_replies']     = $excludeReplies;
        if($contributorDetails !== null) $param['contributor_details'] = $contributorDetails;
        if($includeEntities    !== null) $param['include_entities']    = $includeEntities;

        return self::_getResponseAndCheck('statuses/home_timeline.json', 'GET', $param);
    }

    /**
     * 自分にリプライしてくれたツイート情報を取得
     * - 最新の200個までしか取得できない
     *
     * @param  int   $count              : 取得したい数
     * @param  int   $sinceId            : 指定したID以前の結果を取得
     * @param  int   $maxId              : 指定したID以降の結果を取得
     * @param  bool  $trimUser           : true => ユーザー情報を縮小する
     * @param  bool  $excludeReplies     : true => リプライツイートも取得
     * @param  bool  $contributorDetails : true => ツイートに screen_name が含まれる場合、詳細なデータが取得できる
     * @param  bool  $includeEntities    : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                     : ツイート情報
     */
    public function getReplyTweets($count = null, $sinceId = null, $maxId = null, $trimUser = null, $excludeReplies = null, $contributorDetails = null, $includeEntities = null)
    {
        $param = array();
        if($count              !== null) $param['count']               = $count;
        if($sinceId            !== null) $param['since_id']            = $sinceId;
        if($maxId              !== null) $param['max_id']              = $maxId;
        if($trimUser           !== null) $param['trim_user']           = $trimUser;
        if($excludeReplies     !== null) $param['exclude_replies']     = $excludeReplies;
        if($contributorDetails !== null) $param['contributor_details'] = $contributorDetails;
        if($includeEntities    !== null) $param['include_entities']    = $includeEntities;

        return self::_getResponseAndCheck('statuses/mentions_timeline.json', 'GET', $param);
    }

    /**
     * 自分のお気に入り情報を取得
     * - 最新の200個までしか取得できない
     *
     * @param  int    $userId          : user_id
     * @param  string $screenName      : ユーザー名
     * @param  int    $count           : 取得したい数
     * @param  int    $sinceId         : 指定したID以前の結果を取得
     * @param  int    $maxId           : 指定したID以降の結果を取得
     * @param  bool   $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                   : ツイート情報
     */
    public function getFavoriteTweets($userId = null, $screenName = null, $count = null, $sinceId = null, $maxId = null, $includeEntities = null)
    {
        $param = array();
        if($userId          !== null) $param['user_id']          = $userId;
        if($screenName      !== null) $param['screen_name']      = $screenName;
        if($count           !== null) $param['count']            = $count;
        if($sinceId         !== null) $param['since_id']         = $sinceId;
        if($maxId           !== null) $param['max_id']           = $maxId;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;

        return self::_getResponseAndCheck('favorites/list.json', 'GET', $param);
    }

    /**
     * 任意のユーザーのツイートを取得
     * - 最新の200個までしか取得できない
     *
     * @param  int    $userId             : user_id
     * @param  string $screenName         : ユーザー名
     * @param  int    $count              : 取得したい数
     * @param  int    $sinceId            : 指定したID以前の結果を取得
     * @param  int    $maxId              : 指定したID以降の結果を取得
     * @param  bool   $trimUser           : true => ユーザー情報を縮小する
     * @param  bool   $excludeReplies     : true => リプライツイートも取得
     * @param  bool   $contributorDetails : true => ツイートに screen_name が含まれる場合、詳細なデータが取得できる
     * @param  bool   $includeRts         : true => リツイートも取得
     * @return array                      : ツイート情報
     */
    public function getTweets($userId = null, $screenName = null, $count = null, $sinceId = null, $maxId = null, $trimUser = null, $excludeReplies = null, $contributorDetails = null, $includeRts = null)
    {
        $param = array();
        if($userId             !== null) $param['user_id']             = $userId;
        if($screenName         !== null) $param['screen_name']         = $screenName;
        if($count              !== null) $param['count']               = $count;
        if($sinceId            !== null) $param['since_id']            = $sinceId;
        if($maxId              !== null) $param['max_id']              = $maxId;
        if($trimUser           !== null) $param['trim_user']           = $trimUser;
        if($excludeReplies     !== null) $param['exclude_replies']     = $excludeReplies;
        if($contributorDetails !== null) $param['contributor_details'] = $contributorDetails;
        if($includeRts         !== null) $param['include_rts']         = $includeRts;

        return self::_getResponseAndCheck('statuses/user_timeline.json', 'GET', $param);
    }

    /**
     * ツイート検索
     *
     * @param  string $q               : 検索ワード
     * @param  string $geocode         : 緯度経度で検索範囲を指定する ※ [37.781157,-122.398720,1mi]
     * @param  string $lang            : 検索言語
     * @param  string $locale          : $q で使用している言語を指定
     * @param  string $resultType      : recent => リアルタイム, popular => 人気のあるツイート, mixed => リアルタイムと人気を混ぜた値を返す
     * @param  int    $count           : 取得したい数
     * @param  string $until           : 指定した日付より以前のツイートを検索 ※ [2015-07-19]
     * @param  int    $sinceId         : 指定したID以前の結果を取得
     * @param  int    $maxId           : 指定したID以降の結果を取得
     * @param  bool   $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @param  string $callback        : JSONP形式でレスポンスを受け取ることが出来る
     * @return array                   : [statuses] => 検索結果 , [search_metadata] => 検索結果の最大,最小IDなどのメタ情報
     */
    public function getSearchTweets($q, $geocode = null, $lang = self::DEFAULT_TWEET_LANGUAGE, $locale = self::DEFAULT_TWEET_LANGUAGE, $resultType='recent', $count = null, $until = null, $sinceId = null, $maxId = null, $includeEntities = null, $callback = null)
    {
        $param = array();
        if($q               !== null) $param['q']                = $q;
        if($geocode         !== null) $param['geocode']          = $geocode;
        if($lang            !== null) $param['lang']             = $lang;
        if($locale          !== null) $param['locale']           = $locale;
        if($resultType      !== null) $param['result_type']      = $resultType;
        if($count           !== null) $param['count']            = $count;
        if($until           !== null) $param['until']            = $until;
        if($sinceId         !== null) $param['since_id']         = $sinceId;
        if($maxId           !== null) $param['max_id']           = $maxId;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;
        if($callback        !== null) $param['callback']         = $callback;

        return self::_getResponseAndCheck('search/tweets.json', 'GET', $param);
    }

    /**
     * ダイレクトメッセージを取得
     *
     * @param  int   $id : direct_message.id
     * @return array     : ダイレクトメッセージ情報
     */
    public function getDirectMessage($id)
    {
        return self::_getResponseAndCheck('direct_messages/show.json', 'GET', array('id' => $id));
    }

    /**
     * ダイレクトメッセージを取得(複数)
     *
     * @param  int   $sinceId         : 指定したID以前の結果を取得
     * @param  int   $maxId           : 指定したID以降の結果を取得
     * @param  int   $count           : 取得したい数
     * @param  bool  $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @param  bool  $skipStatus      : true => [status]削除
     * @return array                  : ダイレクトメッセージ情報
     */
    public function getDirectMessages($sinceId = null, $maxId = null, $count = null, $includeEntities = null, $includeEntities = null, $skipStatus = null)
    {
        $param = array();
        if($sinceId         !== null) $param['since_id']         = $sinceId;
        if($maxId           !== null) $param['max_id']           = $maxId;
        if($count           !== null) $param['count']            = $count;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;
        if($skipStatus      !== null) $param['skip_status']      = $skipStatus;

        return self::_getResponseAndCheck('direct_messages.json', 'GET', $param);
    }

    /**
     * 送信したダイレクトメッセージを取得
     *
     * @param  int   $sinceId         : 指定したID以前の結果を取得
     * @param  int   $maxId           : 指定したID以降の結果を取得
     * @param  int   $count           : 取得したい数
     * @param  int   $page            : ページ数
     * @param  bool  $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                  : ダイレクトメッセージ情報(送信)
     */
    public function getSentDirectMessages($sinceId = null, $maxId = null, $count = null, $page = null, $includeEntities = null)
    {
        $param = array();
        if($sinceId         !== null) $param['since_id']         = $sinceId;
        if($maxId           !== null) $param['max_id']           = $maxId;
        if($count           !== null) $param['count']            = $count;
        if($page            !== null) $param['page']             = $page;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;

        return self::_getResponseAndCheck('direct_messages/sent.json', 'GET', $param);
    }

    /**
     * 指定したユーザーの情報を取得
     *
     * @param  int    $userId          : user_id
     * @param  string $screenName      : ユーザー名
     * @param  bool   $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                   : ユーザー情報
     */
    public function getUser($userId = null, $screenName = null, $includeEntities = null)
    {
        $param = array();
        if($userId          !== null) $param['user_id']          = $userId;
        if($screenName      !== null) $param['screen_name']      = $screenName;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;

        return self::_getResponseAndCheck('users/show.json', 'GET', $param);
    }

    /**
     * 複数ユーザーの情報を取得
     *
     * @param  array $userIds         : user_id
     * @param  array $screenNames     : ユーザー名
     * @param  bool  $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                  : ユーザー情報
     */
    public function getUsers(array $userIds = array(), array $screenNames = array(), $includeEntities = null)
    {
        $param = array();
        if(count($userIds)     !== 0)    $param['user_id']          = implode(',', $userIds);
        if(count($screenNames) !== 0)    $param['screen_name']      = implode(',', $screenNames);
        if($includeEntities    !== null) $param['include_entities'] = $includeEntities;

        return self::_getResponseAndCheck('users/lookup.json', 'GET', $param);
    }

    /**
     * 指定したユーザーのフォローしている人たちの情報を取得
     *
     * @param  int    $userId              : user_id
     * @param  string $screenName          : ユーザー名
     * @param  int    $cursor              : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @param  int    $count               : 取得件数
     * @param  bool   $skipStatus          : true => [status]削除
     * @param  bool   $includeUserEntities : false => [entities]削除
     * @return array                       : [users] フォローしているユーザーデータ , [next_cursor] 次ページ $cursor , [previous_cursor] 前ページ $cursor
     */
    public function getFollowUsers($userId = null, $screenName = null, $cursor = null, $count = null, $skipStatus = null, $includeUserEntities = null)
    {
        $param = array();
        if($userId              !== null) $param['user_id']               = $userId;
        if($screenName          !== null) $param['screen_name']           = $screenName;
        if($cursor              !== null) $param['cursor']                = $cursor;
        if($count               !== null) $param['count']                 = $count;
        if($skipStatus          !== null) $param['skip_status']           = $skipStatus;
        if($includeUserEntities !== null) $param['include_user_entities'] = $includeUserEntities;

        return self::_getResponseAndCheck('friends/list.json', 'GET', $param);
    }

    /**
     * 指定したユーザーのフォローしている人たちの情報を取得(user_idのみ)
     *
     * @param  int    $userId       : user_id
     * @param  string $screenName   : ユーザー名
     * @param  int    $cursor       : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @param  int    $count        : 取得件数
     * @param  bool   $stringifyIds : true => 要素が string 型で返される
     */
    public function getFollowUsersIds($userId = null, $screenName = null, $cursor = null, $count = null, $stringifyIds = null)
    {
        $param = array();
        if($userId       !== null) $param['user_id']       = $userId;
        if($screenName   !== null) $param['screen_name']   = $screenName;
        if($cursor       !== null) $param['cursor']        = $cursor;
        if($count        !== null) $param['count']         = $count;
        if($stringifyIds !== null) $param['stringify_ids'] = $stringifyIds;

        return self::_getResponseAndCheck('friends/ids.json', 'GET', $param);
    }

    /**
     * 指定したユーザーのフォロワーたちの情報を取得
     *
     * @param  int    $userId              : user_id
     * @param  string $screenName          : ユーザー名
     * @param  int    $cursor              : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @param  int    $count               : 取得件数
     * @param  bool   $skipStatus          : true => [status]削除
     * @param  bool   $includeUserEntities : false => [entities]削除
     * @return array                       : [users] フォロワーのユーザーデータ , [next_cursor] 次ページ $cursor , [previous_cursor] 前ページ $cursor
     */
    public function getFollowerUsers($userId = null, $screenName = null, $cursor = null, $count = null, $skipStatus = null, $includeUserEntities = null)
    {
        $param = array();
        if($userId              !== null) $param['user_id']               = $userId;
        if($screenName          !== null) $param['screen_name']           = $screenName;
        if($cursor              !== null) $param['cursor']                = $cursor;
        if($count               !== null) $param['count']                 = $count;
        if($skipStatus          !== null) $param['skip_status']           = $skipStatus;
        if($includeUserEntities !== null) $param['include_user_entities'] = $includeUserEntities;

        return self::_getResponseAndCheck('followers/list.json', 'GET', $param);
    }

    /**
     * 指定したユーザーのフォロワーたちの情報を取得(user_idのみ)
     *
     * @param  int    $userId       : user_id
     * @param  string $screenName   : ユーザー名
     * @param  int    $cursor       : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @param  int    $count        : 取得件数
     * @param  bool   $stringifyIds : true => 要素が string 型で返される
     * @return array                : フォロワーのユーザーID [ids] => user_id , [next_cursor] => 次 , [previous_cursor] => 前
     */
    public function getFollowerUsersIds($userId = null, $screenName = null, $cursor = null, $count = null, $skipStatus = null, $includeUserEntities = null)
    {
        $param = array();
        if($userId              !== null) $param['user_id']               = $userId;
        if($screenName          !== null) $param['screen_name']           = $screenName;
        if($cursor              !== null) $param['cursor']                = $cursor;
        if($count               !== null) $param['count']                 = $count;
        if($skipStatus          !== null) $param['skip_status']           = $skipStatus;
        if($includeUserEntities !== null) $param['include_user_entities'] = $includeUserEntities;

        return self::_getResponseAndCheck('followers/ids.json', 'GET', $param);
    }

    /**
     * ユーザー検索
     *
     * @param  string $q                : 検索ワード
     * @param  int    $count            : 取得件数(初期値は20件)
     * @param  int    $page             : ページ数
     * @param  bool   $includeEntities  : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @return array                    : 検索結果のツイート情報
     */
    public function getSearchUsers($q, $page = null, $count = null, $includeEntities = null)
    {
        $param = array();
        if($q               !== null) $param['q']                = $q;
        if($count           !== null) $param['count']            = $count;
        if($page            !== null) $param['page']             = $page;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;

        return self::_getResponseAndCheck('users/search.json', 'GET', $param);
    }

    /**
     * ブロックリスト取得
     *
     * @param  bool   $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @param  bool   $skipStatus      : true => [status]削除
     * @param  int    $cursor          : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @return array                   : ブロックリストに登録されているユーザー情報 [users] => ユーザー情報 , [next_cursor] => 次 , [previous_cursor] => 前
     */
    public function getBlockList($includeEntities = null, $skipStatus = null, $cursor = null)
    {
        $param = array();
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;
        if($skipStatus      !== null) $param['skip_status']      = $skipStatus;
        if($cursor          !== null) $param['cursor']           = $cursor;

        return self::_getResponseAndCheck('blocks/list.json', 'GET', $param);
    }

    /**
     * ブロックリスト取得(user_idのみ)
     *
     * @param  bool   $stringifyIds : true => 要素が string 型で返される
     * @param  int    $cursor       : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @return array                : ブロックリストに登録されているユーザーID [ids] => user_id , [next_cursor] => 次 , [previous_cursor] => 前
     */
    public function getBlockListIds($stringifyIds = null, $cursor = null)
    {
        $param = array();
        if($stringifyIds !== null) $param['stringify_ids'] = $stringifyIds;
        if($cursor       !== null) $param['cursor']        = $cursor;

        return self::_getResponseAndCheck('blocks/ids.json', 'GET', $param);
    }

    /**
     * ミュートリスト取得
     *
     * @param  bool   $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @param  bool   $skipStatus      : true => [status]削除
     * @param  int    $cursor          : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @return array                   : ミュートリストに登録されているユーザー情報 [users] => ユーザー情報 , [next_cursor] => 次 , [previous_cursor] => 前
     */
    public function getMutes($includeEntities = null, $skipStatus = null, $cursor = null)
    {
        $param = array();
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;
        if($skipStatus      !== null) $param['skip_status']      = $skipStatus;
        if($cursor          !== null) $param['cursor']           = $cursor;

        return self::_getResponseAndCheck('mutes/users/list.json', 'GET', $param);
    }

    /**
     * ミュートリスト取得(user_idのみ)
     *
     * @param  int   $cursor : ページングを実装 next_cursor, previous_cursor に入っている数字を入れる ※ [next_cursor] => 次 , [previous_cursor] => 前
     * @return array         : ミュートリストに登録されているユーザーID [ids] => user_id , [next_cursor] => 次 , [previous_cursor] => 前
     */
    public function getMutesIds($cursor = null)
    {
        $param = array();
        if($cursor !== null) $param['cursor'] = $cursor;

        return self::_getResponseAndCheck('mutes/users/ids.json', 'GET', $param);
    }

    /**
     * ツイート削除
     *
     * @param  int  $id : id
     * @return array    : ツイート情報
     */
    public function deleteTweet($id)
    {
        return self::_getResponseAndCheck("statuses/destroy/{$id}.json", 'POST');
    }

    /**
     * リツイート削除
     * - リツイート削除はツイートを削除する事と同じ
     *
     * @param  int  $id : id
     * @return array    : ツイート情報
     */
    public function deleteReTweet($id)
    {
        return self::deleteTweet($id);
    }

    /**
     * お気に入り削除
     *
     * @param  int  $id : id
     * @return array    : ツイート情報
     */
    public function deleteFavorite($id)
    {
        return self::_getResponseAndCheck('favorites/destroy.json', 'POST', array('id' => $id));
    }

    /**
     * ダイレクトメッセージ削除
     *
     * @param  int  $id : 削除したいID
     * @return array    : ダイレクトメッセージ情報
     */
    public function deleteDirectMessage($id)
    {
        return self::_getResponseAndCheck('direct_messages/destroy.json', 'POST', array('id' => $id));
    }

    /**
     * フォローを外す
     *
     * @param  int    $userId     : user_id
     * @param  string $screenName : ユーザー名
     * @return array              : ユーザー情報
     */
    public function deleteFollow($userId = null, $screenName = null)
    {
        $param = array();
        if($userId     !== null) $param['user_id']     = $userId;
        if($screenName !== null) $param['screen_name'] = $screenName;

        return self::_getResponseAndCheck('friendships/destroy.json', 'POST', $param);
    }

    /**
     * ブロックリスト削除
     *
     * @param  int    $userId          : user_id
     * @param  string $screenName      : ユーザー名
     * @param  bool   $includeEntities : true => [entities] という項目が増えてより詳細な情報が取得できる
     * @param  bool   $skipStatus      : true => [status]削除
     * @return array                   : ユーザー情報
     */
    public function deleteBlockList($userId = null, $screenName = null, $includeEntities = null, $skipStatus = null)
    {
        $param = array();
        if($userId          !== null) $param['user_id']          = $userId;
        if($screenName      !== null) $param['screen_name']      = $screenName;
        if($includeEntities !== null) $param['include_entities'] = $includeEntities;
        if($skipStatus      !== null) $param['skip_status']      = $skipStatus;

        return self::_getResponseAndCheck('blocks/destroy.json', 'POST', $param);
    }

    /**
     * ミュートリスト削除
     *
     * @param  int    $userId     : user_id
     * @param  string $screenName : ユーザー名
     * @return array              : ユーザー情報
     */
    public function deleteMutes($userId = null, $screenName = null)
    {
        $param = array();
        if($userId     !== null) $param['user_id']     = $userId;
        if($screenName !== null) $param['screen_name'] = $screenName;

        return self::_getResponseAndCheck('mutes/users/destroy.json', 'POST', $param);
    }

    /**
     * Twitterインスタンスをセット
     */
    private function _setTwitterOAuth(array $account)
    {
        $this->apiKey = $account['api_key'];

        if(!isset(self::$twitter[$this->apiKey]))
        {
            self::$twitter[$this->apiKey] = new TwitterOAuth(
                            $account['api_key'],
                            $account['api_secret'],
                            $account['access_token'],
                            $account['access_token_secret']
                        );
        }
    }

    /**
     * レスポンスがjsonなので配列に直して返す
     *
     * @param  string $api        : 使用するAPI
     * @param  string $method     : HTTPメソッド
     * @param  array  $parameters : パラメータ
     * @param  int    $sleepTime  : 送信間隔(1/1000000秒)
     * @return array              : レスポンス
     */
    private function _callApi($api, $method, array $parameters = array(), $sleepTime=self::SLEEP_TIME)
    {
        usleep($sleepTime); // スリープ時間を設ける
        return json_decode(self::$twitter[$this->apiKey]->OAuthRequest("https://api.twitter.com/1.1/{$api}", $method, $parameters), true);
    }

    /**
     * レスポンスの形式が正しいかチェック
     * - 問題があった場合は例外を投げる
     * - 問題が無かった場合はレスポンスを返す
     *
     * @param  string $api        : 使用するAPI
     * @param  string $method     : HTTPメソッド
     * @param  array  $parameters : パラメータ
     * @param  int    $sleepTime  : 送信間隔(1/1000000秒)
     * @return array              : レスポンス
     */
    private function _getResponseAndCheck($api, $method, array $parameters = array(), $sleepTime=self::SLEEP_TIME)
    {
        $response = self::_callApi($api, $method, $parameters, $sleepTime);

        if(! is_array($response))
        {
            throw new Exception('error response');
        }

        if(isset($response['errors']) || isset($response['error']))
        {
            throw new Exception(self::_getErrorMessage($response));
        }
        return $response;
    }

    /**
     * エラーメッセージが配列なので文字列にする
     *
     * @param  array $response : APIから取得したレスポンス
     * @return string          : エラーメッセージ
     */
    private function _getErrorMessage($response)
    {
        $errorMessage = '';

        if(isset($response['errors']))
        {
            foreach($response['errors'] as $k => $v)
            {
                $errorMessage .= 'code => ' .    $v['code'] . "\n";
                $errorMessage .= 'message => ' . $v['message'] . "\n";
            }
        }else
        {
            $errorMessage .= 'code => ' .    $response['error']['code'] . "\n";
            $errorMessage .= 'message => ' . $response['error']['message'] . "\n";
        }
        return $errorMessage;
    }
}
