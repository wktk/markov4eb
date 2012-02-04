//==================================================================================================
// このファイルの中身を EasyBotter.php の class EasyBotter 内に貼りつけてください。
// v0.18  <?php
    
    function markov($appid){
        // タイムライン取得
        $timeline = $this->selectTweets($this->getFriendsTimeline());
        
        if ((bool)$timeline) {
            // 連鎖テーブル作成
            $head   = array();
            $words  = array();
            $markov = array();
            
            foreach ($timeline as $tweet) {
                
                // 単語毎に切る
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
            echo "EasyMarkov (Tweet) > ". $create_text. "<br />";
            
            // フッターを結合
            $status = $create_text. $this->_footer;
            
            // 投稿して結果表示
            $response = $this->setUpdate(array("status" => $status));
            $result   = $this->showResult($response);
            
        } else {
            // 選別後のタイムラインが空だったとき
            $result = "EasyMarkov (Tweet) > 連鎖に使用できるツイートが TL にありませんでした。<br />";
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
        $timeline = array_reverse($this->selectTweets($this->getFriendsTimeline()));
        
        if ((bool)$replies) {
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
            
            if ((bool)$replies) {
                if ((bool)$timeline) {
                    
                    // 連鎖テーブル作成
                    $head   = array();
                    $markov = array();
                    $words  = array();
                    
                    foreach ($timeline as $tweet) {
                        // 他アカウントに@しないように元ツイートから @screen_name っぽい文字列を削除
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
                            
                            // 連結した単語数が少ない時はやり直し(空リプライ回避)
                            if ($i > 2) { break; }
                        }
                        
                        // 出来た文章を表示
                        echo "EasyMarkov (Reply) > ". $create_text. "<br />";
                        
                        // 相手ユーザー名とフッターを結合
                        $status = "@".$reply_name." ". $create_text. $this->_footer;
                        
                        // リプライを送信
                        $response = $this->setUpdate(array(
                            'status'=>$status,
                            'in_reply_to_status_id'=>$in_reply_to_status_id,
                        ));
                        
                        // 結果を表示
                        $result = $this->showResult($response);
                        $results[] = $result;
                        
                        // リプライ成功ならリプライ済みツイートに登録
                        if($response->in_reply_to_status_id){
                            $this->_repliedReplies[] = (string)$response->in_reply_to_status_id;
                        }
                    }
                }else{
                    // (選別後の) タイムラインが空だったとき
                    $result = "EasyMarkov (Reply) > 連鎖に使用できるツイートが TL にありませんでした。<br />";
                    echo $result;
                    $results[] = $result;
            }   }
        }else{
            // 新しいリプライを貰ってないとき
            $result = 'EasyMarkov (Reply) > '. $cron." 分以内に受け取った@はないようです。<br />";
            echo $result;
            $results[] = $result;
        }
        
        // リプライしたものがあれば記録
        if(!empty($this->_repliedReplies)){
            $this->saveLog();
        }
        return $results;
    }
    
    function _mMAParse($text, $appid){
        // Yahoo! に文章を送って、形態素解析の結果を取得する関数
        
        require_once 'HTTP/Request2.php';
        $url = 'http://jlp.yahooapis.jp/MAService/V1/parse';
        $http = new HTTP_Request2($url, HTTP_Request2::METHOD_POST);
        
        // パラメータの設定
        $http->addPostParameter( array(
            'results'   => 'ma',
            'appid'     =>  $appid,
            'response'  => 'surface,pos',
            'sentence'  =>  $text,
        ));
        
        // 送信して取得
        return $http->send()->getBody();
    }
    
    function _mRemove($text){
        // 都合の悪い文字列を消す関数
        
        // エスケープを解除
        $text = str_replace('&amp;', '&', $text);
        
        // 元ツイートから URL と半角 # を除いておく
        $text = preg_replace('/https?:[-_#!~*\'()a-zA-Z0-9;\/?:\.\@&=+\$,%#]+|#/', '', $text);
        
        // ハッシュタグ削除をしたくない場合は代わりに↓を使用 (無関係な話題を HT に載せるおそれあり)
        // $text = preg_replace('/https?:[-_#!~*\'()a-zA-Z0-9;\/?:\.\@&=+\$,%#]+/', '', $text);
        
        // 英語 HT だけ削除したい (日本語 HT を残せば、HT を連鎖で生成するかも) 場合は代わりに↓を使用
        // $text = preg_replace('/https?:[-_#!~*\'()a-zA-Z0-9;\/?:\.\@&=+\$,%#]+|#[a-zA-Z0-9_]+/', '', $text);
        
        return $text;
    }
    
    function _mXmlParse($xml, $pat){
        // 解析結果から欲しい情報を取り出すための関数
        preg_match_all("/<".$pat.">(.+?)<\/".$pat.">/", $xml, $match);
        return $match[1];
    }
    
//==================================================================================================