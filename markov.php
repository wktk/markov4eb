/*==================================================================================================
 * このファイルの中身を EasyBotter.php の class EasyBotter 内に貼りつけてください。
 * このファイルの 100 行目付近まではカスタムできる項目があります。
 * 
 * https://github.com/wktk/markov4eb (v1.36)
 * https://twitter.com/wktk
 *
 *<?php //*/
    
    // 都合の悪い文字列を削除する関数
    function _mRemove($text) {
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
            // コメントアウトすると通常ツイート時に突然リプライを飛ばせます
            '/(?:@|＠)\w+/',
            
            // URL
            '|https?://t.co/\w+|i',
            
            // ハッシュタグ
            //'/#|＃/',      // ハッシュタグ化をすべて回避する場合
            '/(?:#|＃)\w+/', // 日本語ハッシュタグを生成したい場合,
            
            // RT, QT 以降の文字列
            '/\s*[RQ]T.*$/is',
            
            // '/このように{5,12}/',
            // '/[,\s]*カンマ[^区切りで]+/',
            // '/正規(?:表現|を)追加できます/',
            
        ), '', $text); // マッチした部分を全て削除
        
        // 連続する空白をまとめる
        $text = preg_replace('/\s+/', ' ', $text);
        
        return $text;
    }
    
    // タイムラインから拾うツイートをマルコフ連鎖用に選別する関数
    function _mTLChk($tl) {
        
        // 選別しない場合は次行を利用
        // return $tl;
        
        $tl_ = array();
        foreach ($tl as $tweet) {
            $tweet->source = preg_replace('/<[^>]+>/', '', $tweet->source);
            if (false
                
                // いずれかの条件式が真になる場合拾いません
                // 偽で拾わない様にしたい場合はその条件式の前に ! を付けて下さい
                
                // bot のツイート
                || $tweet->source == 'twittbot.net'
                || $tweet->source == 'EasyBotter'
                || $tweet->source == 'Easybotter'
                || $tweet->source == 'ツイ助。'
                || $tweet->source == 'MySweetBot'
                || $tweet->source == 'BotMaker'
                
                // 鍵垢の方
                || $tweet->user->protected == "true"
                
                // bot 自身
                || $tweet->user->screen_name == $this->_screen_name
                
                // 公式 RT
                || preg_match('/^RT/', $tweet->text)
                
                // 以下は TL 選別の設定例です
                // 試してないのでうまく動かないかも知れません
                
                // @wktk のツイートは拾わない
                //|| $tweet->user->screen_name == "wktk"
                
                // プロフィールの名前が正規表現にマッチしない方
                //||!preg_match('/[a-zA-Z]{5,}/', $tweet->user->name)
                
                // 設定言語が日本語でない方
                //||!$tweet->user->lang == "ja"
                
                // プロフィールの紹介文に 転載 を含む方
                //||stripos($tweet->user->description, '転載')
                
                // デフォルトアイコン (タマゴ) の方
                //|| $tweet->user->default_profile_image == "true"
                
                // フォロー比が高すぎる方
                //|| (int)$tweet->user->friends_count / ((int)$tweet->user->followers_count + 1) > 10
                
                // 画像や動画に不適切な内容を含む可能性のあるツイート
                //|| $tweet->possibly_sensitive == "true"
            ) {}
            
            // 他は拾う
            else $tl_[] = $tweet;
        }
        return $tl_;
    }
    
    // マルコフ連鎖でツイートする関数
    function markov($appid, $endpoint = "http://api.twitter.com/1/statuses/home_timeline.xml?count=30", $table = array()) {
        $timeline = array();
        if (!$table) {
            // タイムライン取得
            $timeline = $this->_mTLChk($this->_getData($endpoint));
            
            // TL があるか調べる
            if (!$timeline) {
                $result = "EasyMarkov (Tweet) &gt; 連鎖に使用できるツイートが TL にありませんでした。<br />\n";
                echo $result;
                return $result;
            }
            
            $tweets = array();
            foreach ($timeline as &$tweet) {
                // 文字列のエスケープ
                $text = $tweet = $this->_mRemove((string)$tweet->text);
                
                // ツイート内で拾ったユーザー名の、ランダム英文字列への置き換え
                //  (形態素解析 API の仕様により、一部のユーザー名が
                //  バラバラになることがあるので一時的に置き換えます)
                $exc = array();
                while (preg_match('/(?:@|＠)\w+/', $text, $matches)) {
                    $str  = str_replace(range(0, 9), '', uniqid());
                    $text = str_replace($matches[0], $str, $text);
                    $exc[$str] = $matches[0];
                }
                
                // 元ツイートを Yahoo! に送って、その解析結果を取得
                $resp = $this->_mMAParse($text, $appid);
                
                // 置き換えたものを元に戻す
                foreach ($exc as $key => $val) $resp = str_replace($key, $val, $resp);
                
                // 単語毎に切る
                $tweets[] = $this->_mXmlParse($resp, 'surface');
            }
            unset($tweet);
            
            // 連鎖用の表にする
            $table = $this->_mTable($tweets);
        }
        
        // マルコフ連鎖で文をつくる
        $status = $this->_mCreate($table, $timeline);
        
        // 出来た文章を表示
        echo "EasyMarkov (Tweet) &gt; ". htmlspecialchars($status). "<br />\n";
        
        // 投稿して結果表示
        $this->showResult($this->setUpdate(array("status" => $status)));
        return $table;
    }
    
    // マルコフ連鎖でリプライする関数
    function replymarkov($cron = 2, $appid, $endpoint = "http://api.twitter.com/1/statuses/home_timeline.xml?count=30", $table = array()) {
        // リプライを取得・選別
        $replies = $this->getReplies();
        $replies = $this->getRecentTweets($replies, $cron * $this->_replyLoopLimit * 3);
        $replies = $this->getRecentTweets($replies, $cron);
        $replies = $this->selectTweets($replies);
        $replies = $this->removeRepliedTweets($replies);
        $replies = array_reverse($replies);
        
        if (!$replies) {
            $result = "EasyMarkov (Reply) &gt; $cron 分以内に受け取った @ はないようです。<br />\n";
            echo $result;
            return $result; // 以後の処理はしない
        }
        
        $timeline = array();
        if (!$table) {
            // タイムライン取得
            $timeline = $this->_mTLChk($this->_getData($endpoint));
            if (!$timeline) { 
                $result = "EasyMarkov (Reply) &gt; 連鎖に使用できるツイートが TL にありませんでした。<br />\n";
                echo $result;
                return $result;
            }
            
            $tweets = array();
            foreach ($timeline as &$tweet) {
                // @screen_name っぽい文字列を削除
                $tweet = preg_replace('/\s*(?:@|＠)\w+\s*/', '', $tweet->text);
                
                // エスケープ
                $tweet = $this->_mRemove($tweet);
                
                // 単語ごとに切る
                $text  = $this->_mMAParse($tweet, $appid);
                $tweets[] = $this->_mXmlParse($text, 'surface');
            }
            unset($tweet);
            
            // 連鎖用の表にする
            $table = $this->_mTable($tweets);
        }
        
        foreach ($replies as $reply) {
            // マルコフ連鎖で文をつくる
            $status = $this->_mCreate($table, $timeline, "@{$reply->user->screen_name} ");
            
            // 出来た文章を表示
            echo "EasyMarkov (Reply) &gt; ". htmlspecialchars($status). "<br />\n";
            
            // リプライを送信
            $response = $this->setUpdate(array(
                'status' => $status,
                'in_reply_to_status_id' => (string)$reply->id,
            ));
            
            // 結果を表示
            $results[] = $this->showResult($response);
            
            // リプライ成功ならリプライ済みツイートに登録
            if ($response->in_reply_to_status_id)
                $this->_repliedReplies[] = (string)$response->in_reply_to_status_id;
        }
        
        if (!empty($this->_repliedReplies)) $this->saveLog();
        return $table;
    }
    
    // Yahoo! に文章を送って、形態素解析の結果を取得する関数
    function _mMAParse($text, $appid) {
        $url  = 'http://jlp.yahooapis.jp/MAService/V1/parse';
        $content = http_build_query(array(
            'appid'    => $appid,
            'sentence' => $text,
            'response' => 'surface', // 読みと品詞 (reading, pos) をカット
        ));
        $data = array('http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: ". strlen($content),
            'content' => $content,
        ));
        return file_get_contents($url, false, stream_context_create($data));
    }
    
    // 解析結果から欲しい情報を取り出す関数
    function _mXmlParse($xml, $pat) {
        preg_match_all("/<". $pat. ">(.+?)<\/". $pat. ">/", $xml, $match);
        $words = array();
        foreach ($match[1] as $word) $words[] = html_entity_decode($word, ENT_QUOTES, 'UTF-8');
        return $words;
    }
    
    // マルコフ連鎖のマップをつくる関数
    function _mTable($tweets) {        
        $table = array();
        foreach ($tweets as $words) {
            if (count($words) > 3) {
                $buff = "[[START]]";
                foreach ($words as $word) $buff = $table[$buff][] = $word;
                $table[$buff][] = "[[END]]";
            }
        }
        
        // 表を出力する (デバッグ用)
        $id = uniqid();
        $dump = str_replace(array("    ", '>', '<', "\n"), array('&nbsp;', '&gt;', '&lt;', '<br />'), print_r($table, true));
        echo <<<HTML
<p>
  テーブルを <a onclick="document.getElementById('$id').style.display='block';return false" href="#">表示</a> /
  <a onclick="document.getElementById('$id').style.display='none';return false" href="#">非表示</a>
</p>
<div id='$id' style='display:none'>
  $dump
  <p>
    テーブルを <a onclick="document.getElementById('$id').style.display='block';return false" href="#">表示</a> /
    <a onclick="document.getElementById('$id').style.display='none';return false" href="#">非表示</a>
  </p>
</div>
HTML;
        return $table;
    }
    
    // マップから文を組み立てる関数
    function _mCreate($table, $timeline, $replyto = '') {
        
        // フッタとリプ先ユーザー名の長さ
        $length = mb_strlen($this->_footer. $replyto, "UTF-8");
        
        // 再試行が 50 回目になったら再試行を諦める
        for ($k = 0; $k < 50; $k++) {
            $text = '';
            $word = "[[START]]";
            
            // 連鎖開始
            for ($i = 0; ; $i++) {
                // 単語をランダムに決定
                $word = $table[$word][array_rand($table[$word])];
                
                // 文末なら終える
                if ($word == "[[END]]") break;
                
                // 単語を連結
                $text .= $word;
                
                // 長くなり過ぎたら適当に切って終了
                if (mb_strlen($text, "UTF-8") + $length > 140) {
                    $text = mb_substr($text, 0, 140 - $length, "UTF-8");
                    break;
                }
            }
            
            // 連結後の文章が、元ツイートと全く同じ時は再試行 (丸パクリ削減)
            if (in_array($text, $timeline)) {
                echo "ボツツイート (丸パク気味): ". $text. "\n<br />";
            }
            elseif ($i < 4) {
                echo "ボツツイート (みじかすぎ): ". $text. "\n<br />";
            }
            else break; // 文章決定
        }
        
        // フッタとリプ先も付けて返す
        return $replyto. $text. $this->_footer;
    }
//==================================================================================================
