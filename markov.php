//==================================================================================================
// このファイルの中身を EasyBotter.php の class EasyBotter 内に貼りつけてください。
// このファイルの 120 行目付近まではカスタムできる項目があります。
// v1.22 // 編集時用→ <?php
    
    // 都合の悪い文字列を削除する関数
    function _mRemove($text) {
        // エスケープ (&amp; など) を解除
        $text = html_entity_decode($text);
        
        // 正規表現を利用した削除
        $text = preg_replace(array(
            
            // @screen_name 形式の文字列
            // コメントアウトすると通常ツイート時に突然リプライを飛ばすことがあります
            '/\s*[@＠][a-zA-Z0-9_]+\s*/',
            
            // URL っぽい文字列
            '/https?:[-_#!~*\'()a-zA-Z0-9;\/?:\.\@&=+\$,%#]+/',
            
            // メールアドレスっぽい文字列
            '/[-a-zA-Z0-9\.+_,]+@[-a-zA-Z0-9\.]+\.[a-zA-Z]{2,4}/',
            
            // 電話番号っぽい数字の羅列
            '/\+?\d{0,3}\.?[-\d()]{9,}/',
            
            // ハッシュタグ (日本語 HT を生成したい場合は下段に切り替え)
            '/[#＃]/',
            // '/[#＃][a-zA-Z0-9_]+/',
            
            // '/このように{5,12}/',
            // '/[,\s]*カンマ[^区切りで]+/',
            // '/正規(?:表現|を)追加できます/',
            
        // マッチした部分を全て削除
        ), '', $text);
        
        // 文字列での削除 (NG ワード)
        $text = str_replace(array(
            '殺',
            // 'このように',
            // 'カンマ区切りで',
            // '単語を追加できます',

        // 一致した部分を全て削除
        ), '', $text);
        
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
                || $tweet->source == '★ツイ助★＜無料＞ツイッター多機能便利ツール'
                || $tweet->source == 'MySweetBot'
                || $tweet->source == 'BotMaker'
                
                // 鍵垢の方
                || $tweet->user->protected == "true"
                
                // RT, QT を含むツイート
                || preg_match('/[rqＲｒＱｑ][tｔＴ]/i', $tweet->text)
                
                // bot 自身
                || $tweet->user->screen_name == $this->_screen_name
                
                // 以下は TL 選別の設定例です
                
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
    
    // TL を取得する関数
    function getHomeTimeline() {
        return $this->_getData("https://api.twitter.com/1/statuses/home_timeline.xml");
        
        // 20 件以外の TL のツイートを取得したい場合は上の return をコメントアウトし、
        // 次行後部の count=200 の部分を編集してください。最大値は 200 です
        return $this->_getData("https://api.twitter.com/1/statuses/home_timeline.xml?count=200");
    }
    
    // マルコフ連鎖でツイートする関数
    function markov($appid) {
        // タイムライン取得
        $timeline = $this->_mTLChk($this->getHomeTimeline());
        
        // TL があるか調べる
        if (!(bool)$timeline) {
            $result = "EasyMarkov (Tweet) &gt; 連鎖に使用できるツイートが TL にありませんでした。<br />\n";
            echo $result;
        } else {
            $tweets = array();
            
            foreach ($timeline as $tweet) {
                // 文字列のエスケープ
                $text = $this->_mRemove((string)$tweet->text);
                
                // 自分のユーザー名を消去
                $text = str_replace("@{$this->_screen_name}", '', $text);
                
                // ツイート内で拾ったユーザー名の、ランダム英文字列への置き換え
                //  (利用している形態素解析 API の仕様により、
                //   一部のユーザー名がバラバラになることがあるので
                //   一時的にランダムな文字列に置き換えます)
                $ran = range('a', 'x');
                $exc = array();
                
                // @screen_name を元ツイートの中から見つける
                while (preg_match('/@[a-zA-Z0-9_]+/', $text, $matches)) {
                    
                    // 適当な羅列を作成
                    for ($i = 0, $str = null; $i < 15; $i++) { $str .= $ran[array_rand($ran, 1)]; }
                    
                    // 置き換える
                    $text = str_replace($matches[0], $str, $text);
                    
                    // 覚えておく
                    $exc[$str] = $matches[0];
                }
                
                // 元ツイートを Yahoo! に送って、その解析結果を取得
                $resp = $this->_mMAParse($text, $appid);
                
                // 置き換えたものを元に戻す
                foreach ($exc as $key => $val) { $resp = str_replace($key, $val, $resp); }
                
                // 単語毎に切る
                $words = $this->_mXmlParse($resp, 'surface');
                
                $tweets[] = $words;
            }
            
            // 連鎖用の表にする
            $table = $this->_mTable($tweets);
            
            // マルコフ連鎖で文をつくる
            $status = $this->_mCreate($table, $timeline);
            
            // 出来た文章を表示
            echo "EasyMarkov (Tweet) &gt; ". htmlspecialchars($status). "<br />\n";
            
            // 投稿して結果表示
            $response = $this->setUpdate(array("status" => $status));
            $result   = $this->showResult($response);
        }
        return $result;
    }
    
    // マルコフ連鎖でリプライする関数
    function replymarkov($cron = 2, $appid) {
        // リプライを取得･選別
        $replies = $this->getReplies();
        $replies = $this->getRecentTweets($replies, $cron * $this->_replyLoopLimit * 3);
        $replies = $this->getRecentTweets($replies, $cron);
        $replies = $this->selectTweets($replies);
        $replies = $this->removeRepliedTweets($replies);
        
        // 連鎖用にタイムラインも取得
        $timeline = $this->_mTLChk($this->getHomeTimeline());
        
        // (リプライループ制限？)
        $replyUsers = array();
        foreach ($replies as $reply) {
            $replyUsers[] = (string)$reply->user->screen_name;
        }
        $countReplyUsers = array_count_values($replyUsers);
        $replies_ = array(); 
        foreach ($replies as $reply) {
            $userName = (string)$reply->user->screen_name;
            if ($countReplyUsers[$userName] < $this->_replyLoopLimit) {
                $replies_[] = $reply;
        }   }
        
        // (古いリプライから処理させる？)
        $replies = array_reverse($replies_);
        
        if (!(bool)$replies) {
            // 新しいリプライを貰ってないとき
            $result = "EasyMarkov (Reply) &gt; $cron 分以内に受け取った @ はないようです。<br />\n";
            echo $result;
            $results[] = $result;
        } elseif (!(bool)$timeline) { 
            // (選別後の) タイムラインが空だったとき
            $result = "EasyMarkov (Reply) &gt; 連鎖に使用できるツイートが TL にありませんでした。<br />\n";
            echo $result;
            $results[] = $result;
        } else {
            $tweets = array();
            
            foreach ($timeline as $tweet) {
                // 他アカウントに @ しないように元ツイートから @screen_name っぽい文字列を削除
                $text = preg_replace('/\s*[@＠][a-zA-Z0-9_]+\s*/', '', (string)$tweet->text);
                
                // 単語ごとに切る
                $text  = $this->_mRemove($text);
                $text  = $this->_mMAParse($text, $appid);
                $words = $this->_mXmlParse($text, 'surface');
                $tweets[] = $words;
            }
            
            // 連鎖用の表にする
            $table = $this->_mTable($tweets);
            
            foreach ($replies as $reply) {
                // リプライ先情報を取得
                $in_reply_to_status_id = (string)$reply->id;
                
                // マルコフ連鎖で文をつくる
                $status = $this->_mCreate($table, $timeline, "@{$reply->user->screen_name} ");
                
                // 出来た文章を表示
                echo "EasyMarkov (Reply) &gt; ". htmlspecialchars($status). "<br />\n";
                
                // リプライを送信
                $response = $this->setUpdate(array(
                    'status' => $status,
                    'in_reply_to_status_id' => $in_reply_to_status_id,
                ));
                
                // 結果を表示
                $result = $this->showResult($response);
                $results[] = $result;
                
                // リプライ成功ならリプライ済みツイートに登録
                if ($response->in_reply_to_status_id) {
                    $this->_repliedReplies[] = (string)$response->in_reply_to_status_id;
                }
            } // foreach 各受信済リプ
        } // if タイムラインも選別済リプライもある
        
        // リプライしたものがあれば記録
        if (!empty($this->_repliedReplies)) {
            $this->saveLog();
        }
        return $results;
    }
    
    // Yahoo! に文章を送って、形態素解析の結果を取得する関数
    function _mMAParse($text, $appid) {
        require_once 'HTTP/Request2.php';
        $url  = 'http://jlp.yahooapis.jp/MAService/V1/parse';
        $http = new HTTP_Request2($url, HTTP_Request2::METHOD_POST);
        
        // パラメータの設定
        $http->addPostParameter(array(
            'appid'    => $appid,
            'sentence' => $text,
            'response' => 'surface', // 読みと品詞 (reading,pos) をカット
        ));
        
        // 送信して取得
        return $http->send()->getBody();
    }
    
    // 解析結果から欲しい情報を取り出す関数
    function _mXmlParse($xml, $pat) {
        preg_match_all("/<". $pat. ">(.*)<\/". $pat. ">/", $xml, $match);
        return $match[1];
    }
    
    // マルコフ連鎖のマップをつくる関数
    function _mTable($tweets) {
       /* この形態素解析 API では "[[START]]", "[[END]]" は
        * array("[", "[", "END", "]", "]") の様に分割されるので、
        * 単語に "[[START]]" や "[[END]]" が入ることは考慮しない
        */
        
        $table = array();
        foreach ($tweets as $words) {
            if ((bool)$words) {
                $pre = "[[START]]";
                foreach ($words as $word) {
                    $table[$pre][] = $word;
                    $pre = $word;
                }
                $table[$pre][] = "[[END]]";
            }
        }
        return $table;
    }
    
    // マップから文を組み立てる関数
    function _mCreate($table, $timeline, $replyto = '') {
        // フッタとリプ先ユーザー名の長さ
        $length = mb_strlen($this->_footer. $replyto, "UTF-8");
        
        // 再試行が 10 回目になったら再試行を諦める
        for ($k=0; $k<10; $k++) {
            $text = '';
            $prev = "[[START]]";
            
            // 連鎖開始
            for ($i = 0; ; $i++) {
                // 単語をランダムに決定
                $prev = $table[$prev][array_rand($table[$prev])];
                
                // 文末なら終える
                if ($prev == "[[END]]") { break; }
                
                // 単語を連結
                $text .= $prev;
                
                // 長くなり過ぎたら適当に切って終了
                if (mb_strlen($text, "UTF-8") + $length > 140) {
                    $text = mb_substr($text, 0, 140 - $length, "UTF-8");
                    break;
                }
            }
            
            // 連結後の文章が、元ツイートと全く同じ時は再試行 (丸パクリ削減)
            if (in_array($text, $timeline)) { continue; }
            
            // 連結数が 4 以上なら完成、他は再試行
            if ($i > 3) { break; }
        }
            
        // フッタとリプ先も付けて返す
        return $replyto. $text. $this->_footer;
    }
//==================================================================================================
