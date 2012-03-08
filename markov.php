//==================================================================================================
// このファイルの中身を EasyBotter.php の class EasyBotter 内に貼りつけてください。
// v0.20  <?php
    
    function _mRemove($text){
        // 都合の悪い文字列を消したりする関数
        
        // エスケープ (&amp; など) を解除
        $text = html_entity_decode($text);
        
        // URL っぽい文字列を除く
        $text = preg_replace('/https?:[-_#!~*\'()a-zA-Z0-9;\/?:\.\@&=+\$,%#]+/', '', $text);
        
        // メールアドレスっぽい文字列も削除
        $text = preg_replace('/[-a-zA-Z0-9\.+_,]+@[-a-zA-Z0-9\.]+\.[a-zA-Z]{2,4}/', '', $text);
        
        // 電話番号っぽい数字の羅列も削除
        $text = preg_replace('/\+?\d{0,3}\.?[-\d()]{9,}/', '', $text);
        
        // [HT] 全半角の # を削除 (残したい場合はコメントアウトして下さい)
        $text = str_replace(array('#', '＃'), '', $text);
        
        // 英字で始まる HT だけ削除したい場合は上記 [HT] の代わりに↓を使用
        //  (日本語ハッシュタグを残せば、ハッシュタグを連鎖で生成するかも)
        // $text = preg_replace('/#[a-zA-Z0-9_]+/', '', $text);
        
        // @screen_name 形式の文字列を削除
        // $text = preg_replace('/@[a-zA-Z0-9_]+/', '', $text);
        
        return $text;
    }
    
    function _mChk($tl) {
        // タイムラインから拾うツイートを選別する関数
        
        $tl_ = array();
        foreach ($tl as $tweet) {
            $tweet->source = preg_replace('/<[^>]+>/', '', $tweet->source);
            if (
                // bot のツイートは拾わない
                $tweet->source == 'twittbot.net'
             || $tweet->source == 'EasyBotter'
             || $tweet->source == '★ツイ助★＜無料＞ツイッター多機能便利ツール'
                
                // 鍵垢の方は拾わない
             || (string)$tweet->user->protected == "true"
                
                // デフォルトアイコン (タマゴ) の方は拾わない
             //|| (string)$tweet->user->default_profile_image == "true"
                
                // フォロー比が高すぎる方は拾わない
             //|| (int)$tweet->user->friends_count / ((int)$tweet->user->followers_count + 1) > 10
                
                // 画像や動画に不適切な内容を含む可能性のあるツイートは拾わない
             //|| (string)$tweet->possibly_sensitive == "true"
            ) {}
            
            // 他は拾う
            else $tl_[] = $tweet;
        }
        return $tl_;
    }
    
    function markov($appid){
        // タイムライン取得
        $timeline = $this->_mChk($this->selectTweets($this->getFriendsTimeline()));
        
        if ((bool)$timeline) {
            // 連鎖テーブル作成
            $head   = array();
            $words  = array();
            $markov = array();
            
            foreach ($timeline as $tweet) {
                // 文字列のエスケープ
                $text = $this->_mRemove((string)$tweet->text);
                
                // 自分のユーザー名を消去
                $text = str_replace('@'.$this->_screen_name, '', $text);
                
                // ツイート内で拾ったユーザー名の、ランダム英文字列への置き換え
                //  (Yahoo! JAPAN の形態素解析 API の仕様により、
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
                
                // 文末フラグとして
                $words[] = "\n";
                
                // 連鎖テーブルに単語を追加
                if ((bool)$words) {
                    $pre = null;
                    foreach ($words as $word) {
                        if ($pre === null) {
                            $pre = $word;
                            $head[] = $word;
                        } else {
                            $markov[$pre][] = $word;
                            $pre = $word;
                        }
                    }
                }
            }
            
            // 連鎖開始
            for ($k=0; $k<10; $k++) {
                // やり直しが 10 回目になったらやり直しを諦める
                
                // 1 つ目の単語を決定
                $create_text = $pre = $head[array_rand($head)];
                
                // 単語数を 30 以下に (140 字制限回避)
                for ($i=0 ; $i<30 ; $i++) {
                    // 単語をランダムに決定
                    $pre = $markov[$pre][array_rand($markov[$pre])];
                    
                    // 文の終わり (改行) が来たら連鎖を終える
                    if ($pre == "\n") { break; }
                    
                    // 単語を連結
                    $create_text .= $pre;
                }
                
                // 連結後の文章が、元ツイートと全く同じ時はやり直し
                if (in_array($create_text, $timeline)) { continue; }
                
                // 連結数が 3 以下の時にはやり直し
                if ($i > 3) { break; }
            }
            
            // 出来た文章を表示
            echo "EasyMarkov (Tweet) &gt; ". htmlspecialchars($create_text). "<br />\n";
            
            // フッターを結合
            $status = $create_text. $this->_footer;
            
            // 投稿して結果表示
            $response = $this->setUpdate(array("status" => $status));
            $result   = $this->showResult($response);
            
        } else {
            // 選別後のタイムラインが空だったとき
            $result = "EasyMarkov (Tweet) &gt; 連鎖に使用できるツイートが TL にありませんでした。<br />\n";
            echo $result;
        }
        return $result;
    }
    
    function replymarkov($cron = 2, $appid){
        // リプライを取得･選別
        $replies = $this->getReplies();
        $replies = $this->getRecentTweets($replies, $cron * $this->_replyLoopLimit * 3);
        $replies = $this->getRecentTweets($replies, $cron);
        $replies = $this->selectTweets($replies);
        $replies = $this->removeRepliedTweets($replies);
        
        // 連鎖用にタイムラインも取得
        $timeline = $this->_mChk($this->selectTweets($this->getFriendsTimeline()));
        
        // (リプライループ制限？)
        $replyUsers = array();
        foreach ($replies as $reply) {
            $replyUsers[] = (string)$reply->user->screen_name;
        }
        $countReplyUsers = array_count_values($replyUsers);
        $replies_ = array(); 
        foreach ($replies as $reply){
            $userName = (string)$reply->user->screen_name;
            if ($countReplyUsers[$userName] < $this->_replyLoopLimit) {
                $replies_[] = $reply;
        }   }
        
        // (古いリプライから処理させる？)
        $replies = array_reverse($replies_);
        
        if (!(bool)$replies) {
            // 新しいリプライを貰ってないとき
            $result = 'EasyMarkov (Reply) &gt; '. $cron. " 分以内に受け取った @ はないようです。<br />\n";
            echo $result;
            $results[] = $result;
        } elseif (!(bool)$timeline) { 
            // (選別後の) タイムラインが空だったとき
            $result = "EasyMarkov (Reply) &gt; 連鎖に使用できるツイートが TL にありませんでした。<br />\n";
            echo $result;
            $results[] = $result;
        } else {
            // 連鎖テーブル作成
            $head   = array();
            $markov = array();
            $words  = array();
            
            foreach ($timeline as $tweet) {
                // 他アカウントに @ しないように元ツイートから @screen_name っぽい文字列を削除
                $text = preg_replace('/@[a-zA-Z0-9_]*[a-zA-Z0-9_ ]/', '', (string)$tweet->text);
                
                // 単語ごとに切る
                $text  = $this->_mRemove($text);
                $text  = $this->_mMAParse($text, $appid);
                $words = $this->_mXmlParse($text, 'surface');
                
                // 文末フラグとして
                $words[] = "\n";
                
                // 単語をテーブルに追加
                if ((bool)$words) {
                    $pre = null;
                    foreach ($words as $word) {
                        if ($pre === null) {
                            $pre = $word;
                            $head[] = $word;
                        } else {
                            $markov[$pre][] = $word;
                            $pre = $word;
                        }
                    }
                }
            }
            
            foreach ($replies as $reply) {
                // リプライ先情報を取得
                $reply_name = (string)$reply->user->screen_name;
                $in_reply_to_status_id = (string)$reply->id;
            
                // 連鎖開始
                for ($k=0; $k<10; $k++) {
                    // やり直しが 10 回目になったらやり直しを諦める
                    
                    // 1 つ目の単語をランダムに決定
                    $create_text = $pre = $head[array_rand($head)];
                    
                    // 単語数を 30 以下に (140 字制限回避)
                    for ($i=0 ; $i<30 ; $i++) {
                        // 単語を決める
                        $pre = $markov[$pre][array_rand($markov[$pre])];
                        
                        // 文末なら連鎖終了
                        if ($pre == "\n") { break; }
                        
                        // 単語を連結
                        $create_text .= $pre;
                    }
                    
                    // 連結後の文章が、元ツイートと全く同じ時はやり直し
                    if (in_array($create_text, $timeline)) { continue; }
                    
                    // 連結した単語数が少ない時はやり直し (空リプ削減)
                    if ($i > 2) { break; }
                }
                
                // 出来た文章を表示
                echo "EasyMarkov (Reply) &gt; ". htmlspecialchars($create_text). "<br />\n";
                
                // 相手ユーザー名とフッターを結合
                $status = "@". $reply_name. " ". $create_text. $this->_footer;
                
                // リプライを送信
                $response = $this->setUpdate(array(
                    'status' => $status,
                    'in_reply_to_status_id' => $in_reply_to_status_id,
                ));
                
                // 結果を表示
                $result = $this->showResult($response);
                $results[] = $result;
                
                // リプライ成功ならリプライ済みツイートに登録
                if($response->in_reply_to_status_id){
                    $this->_repliedReplies[] = (string)$response->in_reply_to_status_id;
                }
            } // foreach 各受信済リプ
        } // if タイムラインも選別済リプライもある
        
        // リプライしたものがあれば記録
        if(!empty($this->_repliedReplies)){
            $this->saveLog();
        }
        return $results;
    }
    
    function _mMAParse($text, $appid){
        // Yahoo! に文章を送って、形態素解析の結果を取得する関数
        
        require_once 'HTTP/Request2.php';
        $url  = 'http://jlp.yahooapis.jp/MAService/V1/parse';
        $http = new HTTP_Request2($url, HTTP_Request2::METHOD_POST);
        
        // パラメータの設定
        $http->addPostParameter(array(
            'results'   => 'ma',
            'appid'     =>  $appid,
            'response'  => 'surface,pos',
            'sentence'  =>  $text,
        ));
        
        // 送信して取得
        return $http->send()->getBody();
    }
    
    function _mXmlParse($xml, $pat){
        // 解析結果から欲しい情報を取り出すための関数
        preg_match_all("/<". $pat. ">(.+?)<\/". $pat. ">/", $xml, $match);
        return $match[1];
    }
    
//==================================================================================================