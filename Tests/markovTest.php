<?php

// Load the functions as a class
eval('class Markov4eb {' . file_get_contents(dirname(dirname(__FILE__)) . '/markov.php') . '}');

class Markov4ebTest extends PHPUnit_Framework_TestCase {
  function setUp() {
    $markov = new Markov4eb();
    $markov->_screen_name = 'test_screen_name';
    $markov->_footer = '';
    $this->markov = $markov;

    $this->test_tweets = array(
      '今日は良い天気ですね。',
      '私の誕生日は今日です',
      '吾輩は猫である',
      '名前はまだない。',
    );
    $this->test_splited_tweets = array(
      array('今日', 'は', '良い', '天気', 'です', 'ね', '。'),
      array('私', 'の', '誕生日', 'は', '今日', 'です'),
      array('我輩', 'は', '猫', 'で', 'ある'),
      array('名前', 'は', 'まだ', 'ない', '。'),
    );
    $this->test_table = array(
      '[[START]]' => array('今日', '私', '我輩', '名前'),
      '今日' => array('は', 'です'),
      'は' => array('良い', '今日', '猫', 'まだ'),
      '良い' => array('天気'),
      '天気' => array('です'),
      'です' => array('ね', '[[END]]'),
      'ね' => array('。'),
      '。' => array('[[END]]', '[[END]]'),
      '私' => array('の'),
      'の' => array('誕生日'),
      '誕生日' => array('は'),
      '我輩' => array('は'),
      '猫' => array('で'),
      'で' => array('ある'),
      'ある' => array('[[END]]'),
      '名前' => array('は'),
      'まだ' => array('ない'),
      'ない' => array('。'),
    );
  }

  function testEscape() {
    $this->assertEquals('test', $this->markov->_mEscape('te殺st RT @example: hello'));
    $this->assertEmpty($this->markov->_mEscape('https://t.co/liUNMIW'));
    $this->assertEmpty($this->markov->_mEscape('@example'));
  }

  function testCheckTimeline() {
    $timeline = array(
      // Bot status
      array(
        'id' => 1,
        'source' => '<a href="http://twittbot.net">twittbot.net</a>',
        'text' => 'Hello hello',
        'user' => array(
          'screen_name' => 'example',
          'protected' => false,
        ),
      ),

      // Retweeted status
      array(
        'id' => 2,
        'source' => 'web',
        'text' => 'RT @twitter: Hello hello',
        'user' => array(
          'screen_name' => 'twitterapi',
          'protected' => false,
        ),
      ),

      // Normal status
      array(
        'id' => 3,
        'source' => '<a href="http://wktk.jp">wktk</a>',
        'text' => 'Hello hello',
        'user' => array(
          'screen_name' => 'wktk',
          'protected' => false,
        ),
      ),

      // Protected status
      array(
        'id' => 4,
        'source' => 'web',
        'text' => 'Hello hello',
        'user' => array(
          'screen_name' => 'twitter',
          'protected' => true,
        ),
      ),
    );
    $result = $this->markov->_mCheckTimeline($timeline);
    $this->assertCount(1, $result);
    $this->assertEquals(3, $result[0]['id']);
  }

  function testMarkov() {
    $this->markTestIncomplete();
  }

  function testReplyMarkov() {
    $this->markTestIncomplete();
  }

  function testReplyPatternMarkov() {
    $this->markTestIncomplete();
  }

  function testGetTableByURL() {
    $this->markTestIncomplete();
  }

  function testWakatiAppid() {
    try {
      $this->markov->_mWakati('');
    } catch(Exception $e) {
      return;
    }
    $this->fail('Expected exception was not raised');
  }

  function testWakati() {
    $this->markTestIncomplete();
  }

  function testCreateTable() {
    $actual = $this->markov->_mCreateTable($this->test_splited_tweets);
    $this->expectOutputRegex('//');
    $this->assertEquals($this->test_table, $actual);
  }

  function testBuildSentence() {
    $this->expectOutputRegex('//');

    for ($k=0; $k<1000; $k++) {
      $footer = $this->markov->_footer = rand(0, 1) ? uniqid() : '';
      $replyto = rand(0, 1) ? uniqid() : '';

      $result = $this->markov->_mBuildSentence($this->test_table, $this->test_tweets, $replyto);
      $this->assertLessThanOrEqual(140, mb_strlen($result));

      $result = preg_replace("/^{$replyto}|{$footer}$/", '', $result);
      $this->assertNotContains($result, $this->test_tweets);
    }

    $this->markov->_footer = '';
  }
}
