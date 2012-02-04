coding: UTF-8

マルコフ連鎖 for EasyBotter
  更新:  0.18 (2012-01-23; 2.04beta)
  link:  https://github.com/kww/markov4eb
         https://twitter.com/intent/user?user_id=51408776 (@wktk)

 仕様
    * bot から見たタイムラインから、最新 20 件 (?) のツイートを取得して動作します。
    * デフォルトでは、ツイート内の半角 # と URL は消去されます。
    * 公式 RT と、RT 又は QT を含むツイートは連鎖対象から除かれます。
    * 鍵アカウントのツイートも拾います。
    * 通常ツイートでもリプライをすることがあります。
    * マルコフ連鎖でのリプライも設定できます。

 使用手順
    0. EasyBotter を設置しておいてください。

    1. アプリケーション ID の取得
         「Yahoo! JAPAN デベロッパーネットワーク」のアプリケーション ID を、下の URL から取得してください。
         形態素解析の為に必要です。（取得には、Yahoo! JAPAN にログインできる ID が必要です。）
           https://e.developer.yahoo.co.jp/webservices/register_application
         「アプリケーションの種類」は「認証を必要としないAPIを使ったアプリケーション」、
         「サイトURL」は「URLなし」を選択してください。

    2. EasyBotter.php の変更
         EasyBotter.php の 「class EasyBotter { 」のすぐ下に、markov.php の中身を全て貼り付けてください。

    3. bot.php の変更
         bot.php (名前変更している場合はそちらへ) の、
         $response = $eb->***( ～ ); の並びに、次の文を追加してください。
           通常POST
             $response = $eb->markov( 'YJDN のアプリケーション ID' );
           リプライ
             $response = $eb->replymarkov( cron間隔, 'YJDN のアプリケーション ID' );

    4. これで準備完了です。

