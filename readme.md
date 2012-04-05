マルコフ連鎖 for EasyBotter
==========
v1.26 (2012-03-20; 2.04beta)  
  
EasyMarkov  

https://github.com/wktk/markov4eb  
https://twitter.com/wktk


主な仕様
----------
- bot から見たタイムラインから、最新 30 件のツイートを取得して動作します。
- TL 上のツイート内の英字ハッシュタグや URL などは除去されます。
- RT の内容は連鎖対象から除かれます。
- 鍵アカウントのツイートは拾いません。
- twittbot.net, EasyBotter 等からのツイートは拾いません。
- マルコフ連鎖でのリプライも設定できます。
- 単語への分割に Yahoo! JAPAN の [日本語形態素解析 Web API](http://developer.yahoo.co.jp/webapi/jlp/ma/v1/parse.html) を利用しています。



使用手順
----------
1. __[EasyBotter](http://pha22.net/twitterbot/) の設置__


2. __アプリケーション ID の取得__
    - 「Yahoo! JAPAN デベロッパーネットワーク」のアプリケーション ID を、
      <https://e.developer.yahoo.co.jp/webservices/register_application> から取得してください。  
        - 取得には、Yahoo! JAPAN にログインできる ID が必要です。  
        - 「アプリケーションの種類」は「認証を必要としないAPIを使ったアプリケーション」、「サイトURL」は「URLなし」を選択してください。


3. __EasyBotter.php の変更__
    - *EasyBotter.php* の `class EasyBotter {` の中に、  
      [*markov.php*](https://raw.github.com/wktk/markov4eb/master/markov.php) の中身を全て貼り付けてください。


4. __bot.php の変更__
    - *bot.php* (変更している場合はそちらへ) の、
      `$response = $eb->***( ～ );` の並びに、次の文を追加してください。

        - 通常POST  
          `$response = $eb->markov( 'YJDN のアプリケーション ID' );`

        - リプライ  
          `$response = $eb->replymarkov( cron間隔, 'YJDN のアプリケーション ID' );`

5. __Yahoo! デベロッパーネットワークのクレジット表示__
    - Yahoo! JAPAN の [ソフトウエアに関する規則（ガイドライン）](http://docs.yahoo.co.jp/docs/info/terms/chapter1.html#cf5th) 
      により、Yahoo! JAPAN が提供する API の利用者は、Web サイトにクレジットを表示する必要があります。
      bot 用の Web サイトをお持ちの場合は、[Yahoo!デベロッパーネットワーク - クレジットの表示](http://developer.yahoo.co.jp/attribution/) 
      に従いクレジット表示を行なってください。


6. __これで準備完了です。__
    - おつかれさまでした。

