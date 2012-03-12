マルコフ連鎖 for EasyBotter
==========
v1.22 (2012-03-13; 2.04beta)  
  
EasyMarkov  

https://github.com/wktk/markov4eb  
https://twitter.com/wktk


主な仕様
----------
- bot から見たタイムラインから、最新 20 件のツイートを取得して動作します。
- TL 上のツイート内の # や URL などは除去されます。
- 公式 RT と、RT 又は QT を含むツイートは連鎖対象から除かれます。
- 鍵アカウントのツイートは拾いません。
- 他の bot からのツイート (twittbot.net, EasyBotter 等) は拾いません。
- マルコフ連鎖でのリプライも設定できます。



サンプル
----------
- そのまま設置した例: @[e_markov](https://twitter.com/e_markov)  
- 改造例: @[k9_bot](https://twitter.com/k9_bot)



使用手順
----------
1. __[EasyBotter](http://pha22.net/twitterbot/) の設置__


2. __アプリケーション ID の取得__

    - 「Yahoo! JAPAN デベロッパーネットワーク」のアプリケーション ID を、
      <https://e.developer.yahoo.co.jp/webservices/register_application> から取得してください。  
        - 取得には、Yahoo! JAPAN にログインできる ID が必要です。  
        - 「アプリケーションの種類」は「認証を必要としないAPIを使ったアプリケーション」、「サイトURL」は「URLなし」を選択してください。


3. __EasyBotter.php の変更__

    - *EasyBotter.php* の `class EasyBotter {` のすぐ下に、  
      [*markov.php*](https://raw.github.com/wktk/markov4eb/master/markov.php) の中身を全て貼り付けてください。


4. __bot.php の変更__

    - *bot.php* (名前変更している場合はそちらへ) の、
      `$response = $eb->***( ～ );` の並びに、次の文を追加してください。

        - 通常POST  
          `$response = $eb->markov( 'YJDN のアプリケーション ID' );`

        - リプライ  
          `$response = $eb->replymarkov( cron間隔, 'YJDN のアプリケーション ID' );`


5. __これで準備完了です。__

