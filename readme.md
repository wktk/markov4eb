markov4eb
==========
v1.40 (2013-01-08; 2.1.1)  

https://github.com/wktk/markov4eb  
https://twitter.com/wktk


主な仕様
----------
- bot から見たタイムラインから、最新 30 件のツイートを取得して動作します。
- タイムラインの他にもリストなどからの取得も可能です。
- ツイート内の英字ハッシュタグや URL などは除去されます。
- RT の内容は連鎖対象から除かれます。
- 鍵アカウントのツイートは拾いません。
- twittbot.net, EasyBotter 等からのツイートは拾いません。
- マルコフ連鎖でのリプライも設定できます。
- 単語への分割に Yahoo! JAPAN の [日本語形態素解析 Web API](http://developer.yahoo.co.jp/webapi/jlp/ma/v1/parse.html) を利用しています。


注意
----------
- 拾うことに問題のないツイートだけを読み込む方がベターです。性質上ツイートの一部を引用するため、
  許可を得ずに行うことは、場合によってはあまり好ましくありません。


導入手順
----------
1. __[EasyBotter](http://pha22.net/twitterbot/) の設置__

2. __アプリケーション ID の取得__
    - 「Yahoo! JAPAN デベロッパーネットワーク」のアプリケーション ID を、 <https://e.developer.yahoo.co.jp/webservices/register_application> から取得してください。
        - 取得には、Yahoo! JAPAN にログインできる ID が必要です。
        - 「アプリケーションの種類」は「認証を必要としないAPIを使ったアプリケーション」、「サイトURL」は「URLなし」を選択してください。

3.  __EasyBotter.php の変更__
    - EasyBotter.php の `class EasyBotter {` の中に、 [markov.php](https://raw.github.com/wktk/markov4eb/master/markov.php) の中身を全て貼り付けてください。

4.  __bot.php の変更__
    ```php
    <?php
    require_once 'EasyBotter.php';
    $eb = new EasyBotter();
    $eb->appid = '上で取得したアプリケーション ID';
    
    // 通常 POST
    $eb->markov( 'タイムライン取得 URL' );
    
    // マルコフリプライ
    $eb->replymarkov( 'cron 間隔 (分)', 'タイムライン取得 URL' );
    
    // パターンマッチリプライ、マッチしないものはマルコフリプライ
    $eb->replypatternmarkov( 'cron 間隔 (分)', 'パターンファイル', 'タイムライン取得 URL' );
    ```
    - 値を省略するとデフォルト値が使用されます。
        - cron 間隔: 2 (分)
        - パターンファイル: reply_pattern.php
        - タイムライン取得 URL: `http://api.twitter.com/1.1/statuses/home_timeline.json?count=30`
    - 「タイムライン取得 URL」については後述します。省略すると home_timeline を取得します。

5.  __Yahoo! デベロッパーネットワークのクレジット表示__  
    - Yahoo! JAPAN の [ソフトウエアに関する規則（ガイドライン）](http://docs.yahoo.co.jp/docs/info/terms/chapter1.html#cf5th) により、Yahoo! JAPAN が提供する API の利用者は、Web サイトにクレジットを表示する必要があります。このスクリプトでは、単語への分割のために Yahoo! JAPAN の [日本語形態素解析 Web API](http://developer.yahoo.co.jp/webapi/jlp/ma/v1/parse.html) を利用していますので、 bot 用の Web サイトをお持ちの場合は、[Yahoo!デベロッパーネットワーク - クレジットの表示](http://developer.yahoo.co.jp/attribution/)  に従いクレジット表示を行なってください。

6.  __これで準備完了です。__


タイムライン取得 URL の指定について
----------
文章生成の素材用に取得するツイートの読み込み先を選択できます。  
デフォルト (省略時) では、
  - タイムラインの最新 30 件  
    `http://api.twitter.com/1.1/statuses/home_timeline.json?count=30`

のツイートを連鎖に使用します。  

たとえば、以下のような指定が可能です。
  - タイムラインの最新 10 件  
    `http://api.twitter.com/1.1/statuses/home_timeline.json?count=10`
  - 受け取った @ ツイート最新 30 件  
    `http://api.twitter.com/1.1./statuses/mentions_timeline.json?count=30`
  - @[wktk](https://twitter.com/wktk) の最新 30 件のツイート  
    `http://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=wktk&count=30`
  - @[wktk](https://twitter.com/wktk) のリスト「my-accounts」から最新 30 件  
    リスト名に全角文字や記号などが入っているとうまくいかないかも知れません  
    `http://api.twitter.com/1.1/lists/statuses.json?owner_screen_name=wktk&slug=my-accounts&per_page=30`
  - @[wktk](https://twitter.com/wktk) の fav ったツイートから最新 30 件を読み込む  
    `http://api.twitter.com/1.1/favorites/list.json?count=30&screen_name=wktk`

取得件数の最大値は 200 (Twitter API 側の仕様) ですが、多過ぎると処理の途中でタイムアウトしたり、
形態素解析 API のリクエスト数上限に達するおそれがあります。
取得件数は様子をみて調節してください。
markov4eb では、素材のツイート 1 つごとに形態素解析 API にリクエストが発生します。
