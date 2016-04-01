<?php

require_once 'PHPUnit/autoload.php';
require_once './TwitterApi.php';

class TwitterApiTest extends PHPUnit_Framework_TestCase
{
    private static $testTweet    = 999999999;
    private static $twitterUserA = null;
    private static $twitterUserB = null;
    private static $screenNameA  = '99999_A';
    private static $screenNameB  = '99999_B';
    private static $userA        = array(
                'api_key'             => '99999999999999',
                'api_secret'          => '99999999999999',
                'access_token'        => '99999999999999-99999999999999',
                'access_token_secret' => '99999999999999',
            );
    private static $userB        = array(
                'api_key'             => '00000000000000',
                'api_secret'          => '00000000000000',
                'access_token'        => '00000000000000-00000000000000',
                'access_token_secret' => '00000000000000',
            );

    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('TwitterApiTest');
        $result = PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * インスタンス生成
     */
    protected function setUp()
    {
        self::$twitterUserA = new TwitterApi(self::$userA);
        self::$twitterUserB = new TwitterApi(self::$userB);
    }

    /**
     * 1. つぶやく
     * 2. つぶやいたTweetを取得
     * 3. ツイートを削除
     * 4. もう一度ツイートを取得
     * 5. ちゃんと削除できたかチェック
     */
    public function test_Tweet()
    {
        $tweetText = 'test_tweet_' . date('Y-m-d H:i:s');

        // つぶやく
        $setTweetRes = self::$twitterUserA->setTweet($tweetText);
        $this->assertInternalType('int', $setTweetRes['id']);

        // ツイート取得
        $tweetData = self::$twitterUserA->getTweet($setTweetRes['id']);
        $this->assertEquals($setTweetRes['id'],   $tweetData['id']);
        $this->assertEquals($setTweetRes['text'], $tweetData['text']);

        // ツイート削除
        $deleteTweetRes = self::$twitterUserA->deleteTweet($tweetData['id']);
        $this->assertEquals($setTweetRes['id'],   $deleteTweetRes['id']);
    }

    /**
     * 1. ReTweet
     * 2. ReTweetしたツイート情報を取得
     * 3. ReTweet削除
     * 4. もう一度ReTweetしたツイート情報を取得
     * 5. ちゃんと削除できたかチェック
     */
    public function test_ReTweet()
    {
        $reTweetId = self::$testTweet;

        // ReTweet
        $setReTweetRes = self::$twitterUserA->setReTweet($reTweetId);
        $this->assertEquals($reTweetId, $setReTweetRes['retweeted_status']['id']);

        // ReTweet情報を取得
        $reTweetData = self::$twitterUserA->getTweet($setReTweetRes['id'], null, true);
        $this->assertEquals($setReTweetRes['id'], $reTweetData['id']);

        // ReTweet削除
        $deleteReTweetRes = self::$twitterUserA->deleteReTweet($reTweetData['id']);
        $this->assertEquals($setReTweetRes['id'], $deleteReTweetRes['id']);
    }

    /**
     * 1. ツイートをお気に入りに登録
     * 2. お気に入りに入れたツイート情報を取得
     * 3. お気に入りから削除
     * 4. お気に入りから削除したツイート情報を取得して削除できているか確認
     */
    public function test_Favorite()
    {
        $favoriteTweetId = self::$testTweet;

        // favorite
        $setFavoriteRes = self::$twitterUserA->setFavorite($favoriteTweetId);
        $this->assertEquals($setFavoriteRes['id'], $favoriteTweetId);

        // favorite情報を取得
        $getFavoriteTweetData = self::$twitterUserA->getFavoriteTweets();
        $this->assertEquals($favoriteTweetId, $getFavoriteTweetData[0]['id']);

        // favorite削除
        $deleteFavoriteRes = self::$twitterUserA->deleteFavorite($favoriteTweetId);
        $this->assertEquals($setFavoriteRes['id'], $deleteFavoriteRes['id']);
    }

    /**
     * フォローする
     * 名前を指定してフォローユーザーを取得
     * フォロワー一覧を取得
     * フォローを外す
     * フォローが外れているか確認
     */
    public function test_Follow()
    {
        // フォローする
        $setFollowRes = self::$twitterUserA->setFollow(null, self::$screenNameB);
        $this->assertEquals($setFollowRes['screen_name'], self::$screenNameB);

        // getFollowUsers() , getFollowUsersIds() テスト
        $followUserRes    = self::$twitterUserA->getFollowUsers();
        $followUserIdsRes = self::$twitterUserA->getFollowUsersIds();
        $this->assertEquals(self::$screenNameB, $followUserRes['users'][0]['screen_name']);
        $this->assertEquals($followUserIdsRes['ids'][0], $followUserRes['users'][0]['id']);

        // getFollowerUsers() , getFollowerUsersIds() テスト
        $followerUsersRes    = self::$twitterUserA->getFollowerUsers(null, self::$screenNameB);
        $followerUsersIdsRes = self::$twitterUserA->getFollowerUsersIds(null, self::$screenNameB);
        $this->assertEquals(self::$screenNameA, $followerUsersRes['users'][0]['screen_name']);
        $this->assertEquals($followerUsersIdsRes['ids'][0], $followerUsersRes['users'][0]['id']);

        // フォローユーザー取得
        $followUser = self::$twitterUserA->getUser(null, self::$screenNameB);
        $this->assertTrue($followUser['following']);

        // フォローを外す
        $deleteFollowRes = self::$twitterUserA->deleteFollow(null, self::$screenNameB);
        $this->assertEquals(self::$screenNameB, $deleteFollowRes['screen_name']);
    }

    /**
     * 1. A が B をフォローする
     * 2. B が A をフォローする
     * 3. A が B にダイレクトメッセージ送信
     * 4. B が A にダイレクトメッセージ送信
     * 5. ダイレクトメッセージを取得
     * 6. ダイレクトメッセージを取得(送信APIテスト)
     * 7. ダイレクトメッセージ削除
     * 8. A が B のフォローをやめる
     * 9. B が A のフォローをやめる
     */
    public function test_DirectMessage()
    {
        $textA = 'test_direct_message_' . date('Y-m-d H:i:s') . '_sender_A';
        $textB = 'test_direct_message_' . date('Y-m-d H:i:s') . '_sender_B';

        // A が B をフォローする
        $setMyFollowRes = self::$twitterUserA->setFollow(null, self::$screenNameB);
        $this->assertEquals(self::$screenNameB, $setMyFollowRes['screen_name']);

        // B が A をフォローする
        $setOtherFollowRes = self::$twitterUserB->setFollow(null, self::$screenNameA);
        $this->assertEquals(self::$screenNameA, $setOtherFollowRes['screen_name']);

        // A が B にメッセージ送信
        $setDirectMessageARes = self::$twitterUserA->setDirectMessage(null, self::$screenNameB, $textA);
        $this->assertEquals($textA, $setDirectMessageARes['text']);

        // B が A にメッセージ送信
        $setDirectMessageBRes = self::$twitterUserB->setDirectMessage(null, self::$screenNameA, $textB);
        $this->assertEquals($textB, $setDirectMessageBRes['text']);

        // ダイレクトメッセージ取得 A
        $getDirectMessageARes = self::$twitterUserA->getDirectMessage($setDirectMessageARes['id']);
        $this->assertEquals($textA, $getDirectMessageARes['text']);

        // ダイレクトメッセージ取得(複数) A
        $getDirectMessagesARes = self::$twitterUserA->getDirectMessages();
        $this->assertEquals($textB, $getDirectMessagesARes[0]['text']);

        // ダイレクトメッセージ取得(送信) A
        $getSentDirectMessagesARes = self::$twitterUserA->getSentDirectMessages();
        $this->assertEquals($textA, $getSentDirectMessagesARes[0]['text']);

        // ダイレクトメッセージ削除 A
        $deleteDirectMessageARes = self::$twitterUserA->deleteDirectMessage($setDirectMessageARes['id']);
        $this->assertEquals($textA, $deleteDirectMessageARes['text']);

        // ダイレクトメッセージ削除 B
        $deleteDirectMessageBRes = self::$twitterUserB->deleteDirectMessage($setDirectMessageBRes['id']);
        $this->assertEquals($textB, $deleteDirectMessageBRes['text']);

        // A が B のフォローをやめる
        $deleteFollowARes = self::$twitterUserA->deleteFollow(null, self::$screenNameB);
        $this->assertInternalType('int', $deleteFollowARes['id']);

        // B が A のフォローをやめる
        $deleteFollowBRes = self::$twitterUserB->deleteFollow(null, self::$screenNameA);
        $this->assertInternalType('int', $deleteFollowBRes['id']);
    }

    /**
     * 複数ユーザー情報を取得
     */
    public function test_getUsers()
    {
        $screenNames = array(self::$screenNameA, self::$screenNameB);
        $usersData   = self::$twitterUserA->getUsers(array(), $screenNames);

        $this->assertInternalType('array', $usersData);
        $this->assertEquals($screenNames[0], $usersData[0]['screen_name']);
        $this->assertEquals($screenNames[1], $usersData[1]['screen_name']);
    }

    /**
     * 自分のツイートを取得
     */
    public function test_getHomeTweets()
    {
        $homeTweets = self::$twitterUserA->getHomeTweets(1);
        $this->assertInternalType('int', $homeTweets[0]['id']);
    }

    /**
     * 1. リプライをつけて B がツイート
     * 2. A のリプライ情報を A が取得
     * 3. B のツイートを削除
     */
    public function test_getReplyTweets()
    {
        $myScreenName = self::$screenNameA;
        $tweetText    = "@{$myScreenName} reply_test " . date('Y-m-d H:i:s');

        // リプライをつけて B がツイート
        $setTweetRes = self::$twitterUserB->setTweet($tweetText);
        $this->assertInternalType('int', $setTweetRes['id']);

        // A のリプライ情報を A が取得
        $replyTweets = self::$twitterUserA->getReplyTweets(1);
        $this->assertInternalType('int', $replyTweets[0]['id']);

        // B のツイートを削除
        $deleteTweetRes = self::$twitterUserB->deleteTweet($setTweetRes['id']);
        $this->assertEquals($setTweetRes['id'], $deleteTweetRes['id']);
    }

    /**
     * 任意のユーザーのツイートを取得
     */
    public function test_getTweets()
    {
        $screenName   = self::$screenNameB;
        $getTweetsRes = self::$twitterUserA->getTweets(null, $screenName, 1);
        $this->assertInternalType('int', $getTweetsRes[0]['id']);
    }

    /**
     * ツイート検索
     */
    public function test_getSearchTweets()
    {
        $searchWord      = 'test';
        $searchHitTweets = self::$twitterUserA->getSearchTweets($searchWord, null, 'ja', null, 'recent', 1);
        $this->assertArrayHasKey('statuses', $searchHitTweets);
    }

    /**
     * ユーザー検索
     */
    public function test_getSearchUsers()
    {
        $searchUser     = 'test';
        $searchHitUsers = self::$twitterUserA->getSearchUsers($searchUser, null, 1);
        $this->assertInternalType('int', $searchHitUsers[0]['id']);
    }

    /**
     * ※ 自分のブロックリストのみ取得可能
     *
     * 1. ブロックリスト追加
     * 2. ブロックリスト取得
     * 3. ブロックリスト削除
     */
    public function test_BlockList()
    {
        $screenName = self::$screenNameB;

        // ブロックリスト追加
        $setBlockListRes = self::$twitterUserA->setBlockList(null, $screenName);
        $this->assertInternalType('int', $setBlockListRes['id']);

        // ブロックリスト取得
        $blockListIdsRes = self::$twitterUserA->getBlockListIds();
        $blockListRes    = self::$twitterUserA->getBlockList();
        $this->assertEquals($screenName, $blockListRes['users'][0]['screen_name']);
        $this->assertEquals($blockListIdsRes['ids'][0], $blockListRes['users'][0]['id']);

        // ブロックリスト削除
        $deleteBlockListRes = self::$twitterUserA->deleteBlockList(null, $screenName);
        $this->assertInternalType('int', $deleteBlockListRes['id']);
    }

    /**
     * ※ 自分のミュートリストのみ取得可能
     *
     * 1. ミュートリスト追加
     * 2. ミュートリスト取得
     * 3. ミュートリスト削除
     */
    public function test_Mutes()
    {
        $screenName = self::$screenNameB;

        // ミュートリスト追加
        $setMutesRes = self::$twitterUserA->setMutes(null, $screenName);
        $this->assertInternalType('int', $setMutesRes['id']);

        // ミュートリスト取得
        $MutesIdsRes = self::$twitterUserA->getMutesIds();
        $MutesRes    = self::$twitterUserA->getMutes();
        $this->assertEquals($screenName, $MutesRes['users'][0]['screen_name']);
        $this->assertEquals($MutesIdsRes['ids'][0], $MutesRes['users'][0]['id']);

        // ミュートリスト削除
        $deleteMutesRes = self::$twitterUserA->deleteMutes(null, $screenName);
        $this->assertInternalType('int', $deleteMutesRes['id']);
    }
}
