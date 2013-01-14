/*==================================================================================================
 * このファイルの中身を EasyBotter.php の class EasyBotter 内に貼りつけてください。
 * このファイルの 100 行目付近まではカスタムできる項目があります。
 *
 * https://github.com/wktk/markov4eb (v1.42)
 * https://twitter.com/wktk
 *
 *<?php //*/

    // 都合の悪い文字列を削除する関数
    function _mEscape($text) {
        // HTML エンティティをデコード
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // 文字列での削除 (NG ワード)
        $text = str_replace(array(
            '殺',
            "@{$this->_screen_name}",
            // 'このように',
            // 'カンマ区切りで',
            // '単語を追加できます',

        ), '', $text);  // 一致した部分を全て削除

        // 正規表現を利用した削除
        $text = preg_replace(array(
            // @screen_name 形式の文字列
            // 無効化すると通常ツイート時に @mention を飛ばすことがあります
            '/(?:@|＠)\w+/',

            // URL
            '|https?://t.co/\w+|i',

            // ハッシュタグ
            //'/#|＃/',      // ハッシュタグ化をすべて回避する場合
            '/(?:#|＃)\w+/', // 英語ハッシュタグのみ回避する場合

            // RT, QT 以降の文字列
            '/\s*[RQ]T.*$/is',

            // '/このように{5,12}/',
            // '/[,\s]*カンマ[^区切りで]+/',
            // '/正規(?:表現|を)追加できます/',

        ), '', $text); // マッチした部分を全て削除

        // 連続する空白をまとめる
        $text = preg_replace('/(?:　|\s)+/', ' ', $text);
        $text = preg_replace('/^(?:　|\s)|(?:　|\s)$/', '', $text);

        return $text;
    }

    // タイムラインから拾うツイートをマルコフ連鎖用に選別する関数
    function _mCheckTimeline($tl) {

        // 選別しない場合は次行を利用
        // return $tl;

        $tl_ = array();
        foreach ($tl as $tweet) {
            $tweet['source'] = preg_replace('/<[^>]+>/', '', $tweet['source']);
            if (false

                // 「拾わない」ツイートの条件を設定できます

                // bot のツイート
                || $tweet['source'] == 'twittbot.net'
                || $tweet['source'] == 'EasyBotter'
                || $tweet['source'] == 'Easybotter'
                || $tweet['source'] == 'ツイ助。'
                || $tweet['source'] == 'MySweetBot'
                || $tweet['source'] == 'BotMaker'

                // 鍵垢の方
                || $tweet['user']['protected'] == true

                // bot 自身
                || $tweet['user']['screen_name'] == $this->_screen_name

                // RT
                || preg_match('/^RT/', $tweet['text'])

                // 以下は TL 選別の設定例です
                // 試してないのでうまく動かないかも知れません

                // @wktk のツイートは拾わない
                //|| $tweet['user']['screen_name'] == 'wktk'

                // プロフィールの名前が正規表現にマッチしない方
                //||!preg_match('/[a-zA-Z]{5,}/', $tweet['user']['name'])

                // 設定言語が日本語でない方
                //||!$tweet['user']['lang'] == 'ja'

                // プロフィールの紹介文に 転載 を含む方
                //||stripos($tweet['user']['description'], '転載')

                // デフォルトアイコン (タマゴ) の方
                //|| $tweet['user']['default_profile_image'] == true

                // フォロー比が高すぎる方
                //|| (int)$tweet['user']['friends_count'] / ((int)$tweet['user']['followers_count'] + 1) > 10

                // 画像や動画に不適切な内容を含む可能性のあるツイート
                //|| $tweet['possibly_sensitive'] == true
            ) {}

            // 他は拾う
            else $tl_[] = $tweet;
        }
        return $tl_;
    }

    // マルコフ連鎖でツイートする関数
    function markov($url='http://api.twitter.com/1.1/statuses/home_timeline.json?count=30') {
        // タイムラインからテーブルを生成
        list($table, $timeline) = $this->_mGetTableByURL($url, 'Tweet');
        if (!$table) return $timeline;

        // マルコフ連鎖で文をつくる
        $status = $this->_mBuildSentence($table, $timeline);

        // 出来た文章を表示
        echo 'markov4eb (Tweet) &gt; '. htmlspecialchars($status). "\n";

        // 投稿して結果表示
        $this->showResult($this->setUpdate(array('status' => $status)), $status);
    }

    // マルコフ連鎖でリプライする関数
    function replyMarkov($cron=2, $url='http://api.twitter.com/1.1/statuses/home_timeline.json?count=30') {
        // replyPatternMarkov() のパターンファイルがないものとして扱う
        return $this->replyPatternMarkov($cron, '', $url);
    }

    // パターンにマッチしなかったらマルコフ連鎖でリプライする関数
    function replyPatternMarkov($cron=2, $patternFile='reply_pattern.php', $url='http://api.twitter.com/1.1/statuses/home_timeline.json?count=30') {
        // リプライを取得・選別
        $response = $this->getReplies($this->_latestReply);
        $response = $this->getRecentTweets($response, $cron * $this->_replyLoopLimit * 3);
        $replies = $this->getRecentTweets($response, $cron);
        $replies = $this->selectTweets($replies);

        if (!$replies) {
            $result = "markov4eb (Reply) > $cron 分以内に受け取った @ はないようです。";
            echo htmlspecialchars($result). "\n";
            return $result; // 以後の処理はしない
        }

        // ループチェック
        $replyUsers = array();
        foreach ($response as $r) $replyUsers[] = $r['user']['screen_name'];
        $countReplyUsers = array_count_values($replyUsers);
        $replies2 = array();
        foreach ($replies as $reply) {
            $userName = $reply['user']['screen_name'];
            if ($countReplyUsers[$userName] < $this->_replyLoopLimit) $replies2[] = $reply;
        }

        // 古い順にする
        $replies = array_reverse($replies2);

        if (!$replies) {
            $result = "markov4eb (Reply) > 返答する @ がないようです。";
            echo htmlspecialchars($result). "\n";
            return $result; // 以後の処理はしない
        }

        // パターンファイルの読み込み
        if (empty($this->_replyPatternData[$patternFile]) && !empty($patternFile)){
            $this->_replyPatternData[$patternFile] = $this->readPatternFile($patternFile);
        }

        $results = array();
        $repliedReplies = array();
        foreach ($replies as $reply) {
            $status = '';

            // 指定されたリプライパターンと照合
            foreach ((array)$this->_replyPatternData[$patternFile] as $pattern => $res) {
                if (preg_match("@{$pattern}@u", $reply['text'], $matches)) {
                    $status = $res[array_rand($res)];
                    for ($i=1; $i<count($matches); $i++) {
                        $status = str_replace('$'. $i, $matches[$i], $status);
                    }
                    break;
                }
            }

            // パターンにマッチしたら @username とフッタをつける
            if ($status) $status = "@{$reply['user']['screen_name']} {$status}{$this->_footer}";

            // パターンにマッチしない場合はマルコフ
            else {
                // キャッシュしたテーブルがない場合
                if (!$this->_mtable) {
                    // タイムラインからテーブルを作る
                    list($this->_mtable, $this->_mtl) = $this->_mGetTableByURL($url, 'Reply');
                    if (!$this->_mtable) {
                      $results[] = $this->_mtl;
                      continue; // 次のリプライへ
                    }
                }

                // マルコフ連鎖で文をつくる
                $status = $this->_mBuildSentence($this->_mtable, $this->_mtl, "@{$reply['user']['screen_name']} ");

                // 出来た文章を表示
                echo 'markov4eb (Reply) &gt; '. htmlspecialchars($status). "\n";
            }

            // リプライを送信
            $response = $this->setUpdate(array(
                'status' => $status,
                'in_reply_to_status_id' => $reply['id_str'],
            ));

            // 結果を表示
            $results[] = $this->showResult($response, $status);

            // リプライ成功ならリプライ済みツイートに登録
            if ($response['in_reply_to_status_id']) {
                $this->saveLog('latest_reply', $response['in_reply_to_status_id_str']);
                $repliedReplies[] = $response['in_reply_to_status_id_str'];
            }
        }

        unset($this->_mtable, $this->_mtl);
        if (!empty($repliedReplies)) {
            rsort($repliedReplies);
            $this->saveLog('latest_reply', $repliedReplies[0]);
        }
    }

    // タイムラインの URL から連鎖用テーブルを作る関数
    function _mGetTableByURL($url, $type) {
        // タイムライン取得
        $timeline = $this->_mCheckTimeline((array)$this->_getData($url));
        if (!$timeline) {
            $result = "markov4eb ({$type}) > 連鎖に使用できるツイートが TL にありませんでした。";
            echo htmlspecialchars($result). "\n";
            return array(false, $result);
        }

        // ツイートを単語ごとに区切る
        $tweets = array();
        foreach ($timeline as &$tweet) {
            $tweet = $tweet['text'];

            // リプライのときは @screen_name っぽい文字列を削除
            if (preg_match('/reply/i', $type)) {
                $tweet = preg_replace('/\s*(?:@|＠)\w+\s*/', '', $tweet);
            }

            // エスケープ
            $tweet = $this->_mEscape($tweet);

            // 単語ごとに切る
            $tweets[] = $this->_mWakati($tweet);
        }
        unset($tweet);

        // 連鎖用の表にする
        $table = $this->_mCreateTable($tweets);

        return array($table, $timeline);
    }

    // 日本語の文章を分かち書きする関数
    function _mWakati($text) {
        if (empty($this->appid)) trigger_error('markov4eb: appid がセットされていません。$eb->appid = &quot;Your app id&quot;; の形式で、appid を設定してください。', E_USER_ERROR);

        // @username のランダム英文字列への一時的な置き換え
        $map = array();
        while (preg_match('/(?:@|＠)\w+/', $text, $matches)) {
            $str = str_replace(range(0, 9), '', uniqid());
            $text = str_replace($matches[0], $str, $text);
            $map[$str] = $matches[0];
        }

        // 形態素解析 API へのリクエスト
        // ドキュメント: "テキスト解析:日本語形態素解析API - Yahoo!デベロッパーネットワーク"
        //   http://developer.yahoo.co.jp/webapi/jlp/ma/v1/parse.html
        $url = 'http://jlp.yahooapis.jp/MAService/V1/parse';
        $content = http_build_query(array(
            'appid'    => $this->appid,
            'sentence' => $text,
            'results'  => 'ma', // 形態素解析の結果を取得する
            'response' => 'surface', // 読みと品詞 (reading, pos) をカット
        ));
        $data = array('http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: ". strlen($content),
            'content' => $content,
        ));
        $response = file_get_contents($url, false, stream_context_create($data));

        // 置き換えたものを元に戻す
        $response = str_replace(array_keys($map), array_values($map), $response);

        // 単語の配列をつくる
        $xml = simplexml_load_string($response);
        $words = array();
        foreach ($xml->ma_result->word_list->word as $word) $words[] = (string)$word->surface;
        return $words;
    }

    // マルコフ連鎖のマップをつくる関数
    function _mCreateTable($tweets) {
        $table = array();
        foreach ($tweets as $words) {
            if (count($words) > 3) {
                $buff = '[[START]]';
                foreach ($words as $word) $buff = $table[$buff][] = $word;
                $table[$buff][] = '[[END]]';
            }
        }

        // 表を出力する (デバッグ用)
        $id = uniqid();
        $dump = str_replace(array('    ', '>', '<'), array('&nbsp;', '&gt;', '&lt;'), print_r($table, true));
        echo <<<HTML
<p>
  テーブルを <a onclick="document.getElementById('$id').style.display='block';return false" href="#">表示</a> / <a onclick="document.getElementById('$id').style.display='none';return false" href="#">非表示</a>
</p>
<div id='$id' style='display:none'>
  $dump
  <p>
    テーブルを <a onclick="document.getElementById('$id').style.display='block';return false" href="#">表示</a> / <a onclick="document.getElementById('$id').style.display='none';return false" href="#">非表示</a>
  </p>
</div>
HTML;
        return $table;
    }

    // マップから文を組み立てる関数
    function _mBuildSentence($table, $timeline, $replyto='') {
        // フッタとリプ先ユーザー名の長さ
        if (function_exists('mb_strlen')) {
            $length = mb_strlen($this->_footer. $replyto, 'UTF-8');
        } else {
            $length = strlen($this->_footer. $replyto);
        }

        // 再試行が 50 回目になったら再試行を諦める
        for ($k = 0; $k < 50; $k++) {
            $text = '';
            $word = '[[START]]';

            // 連鎖開始
            for ($i = 0; ; $i++) {
                // 単語をランダムに決定
                $word = $table[$word][array_rand($table[$word])];

                // 文末なら終える
                if ($word == '[[END]]') break;

                // 単語を連結
                $text .= $word;

                // 長くなり過ぎたら適当に切って終了
                if (function_exists('mb_strlen')) {
                    if (mb_strlen($text, 'UTF-8') + $length > 140) {
                        $text = mb_substr($text, 0, 140 - $length, 'UTF-8');
                        break;
                    }
                } else {
                    if (strlen($text) + $length > 140)  {
                        $text = substr($text, 0, 140 - $length);
                        break;
                    }
                }
            }

            // 連結後の文章が、元ツイートと全く同じ時は再試行 (丸パクリ削減)
            if (in_array($text, $timeline)) {
                echo 'ボツツイート (丸パク気味): '. htmlspecialchars($text). "\n";
            }
            elseif ($i < 4) {
                echo 'ボツツイート (みじかすぎ): '. htmlspecialchars($text). "\n";
            }
            else break; // 文章決定
        }

        // フッタとリプ先も付けて返す
        return $replyto. $text. $this->_footer;
    }
//==================================================================================================
