<?php

class Ttrss_hatebu extends Plugin
{
    function about()
    {
        return [
            1.0,
            "はてなブックマークのエントリーを見る",
            "nathurru",
        ];
    }

    function init($host)
    {
        $host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
    }

    function get_js()
    {
        return file_get_contents(__DIR__ . "/hatebu.js");
    }

    function hook_article_button($line)
    {
        $article_id = $line['id'];

        return "<i style=\"width:16px; height:16px; cursor:pointer; background:url(plugins.local/ttrss_hatebu/hatebu.png) 0 0 no-repeat; background-size:100%; overflow:hidden; text-indent:100%;\" title=\"はてなブックマークのエントリーを見る\" onclick=\"Plugins.Hatebu.view($article_id)\">hatebu</i>";
    }

    function getHatebuInfo()
    {
        try {
            $articleLink = rawurlencode($this->getLink($_REQUEST['id']));
        } catch (Exception $e) {
            print 'DB Error: ' . $e->getMessage();
            return;
        }

        try {
            $endpoint = 'https://b.hatena.ne.jp/entry/jsonlite/?url=' . $articleLink;
            $result = $this->getEntry($endpoint);
        } catch (Exception $e) {
            print 'cURL Error: ' . $e->getMessage();
            return;
        }

        $entry = json_decode($result, true);

        if (is_null($entry) || $entry['count'] === 0) {
            print $this->generateLink('https://b.hatena.ne.jp/add?url=' . $articleLink, '0 users') . ' / 0 comments';
        } else {
            $comments = $this->generateComment($entry['bookmarks'], $entry['eid']);
            $count = count($comments);
            print $this->generateLink($entry['entry_url'], $entry['count'] . ' users') . ' / ' . $count . ' comments';
            if($count > 0) {
                print '<ol>';
                foreach ($comments as $comment) {
                    print $comment;
                }
                print '</ol>';
            }

        }
    }

    function getEntry($url)
    {
        $cURL = curl_init();
        curl_setopt($cURL, CURLOPT_URL, $url);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 10);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_FAILONERROR, true);

        $result = curl_exec($cURL);

        $errno = curl_errno($cURL);
        $error = curl_error($cURL);

        curl_close($cURL);
        if (CURLE_OK !== $errno) {
            throw new ErrorException($error);
        }

        return $result;
    }

    function getLink($id)
    {
        $sth = $this->pdo->prepare("SELECT link 
									FROM ttrss_entries, ttrss_user_entries 
									WHERE id = ? AND ref_id = id  AND owner_uid = ?");
        $sth->execute([$id, $_SESSION['uid']]);
        $row = $sth->fetch();

        return $row['link'];
    }

    function generateLink($url, $text)
    {
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
    }

    function generateComment($bookmarks, $eid)
    {
        $comments = [];

        foreach ($bookmarks as $bookmark) {
            if ($bookmark['comment'] !== '') {
                $userInfo = $this->generateLink($this->generateUserPageUrl($bookmark['user']), '<img src="' . $this->generateUserIconUrl($bookmark['user']) . '" alt="' . $bookmark['user'] . '">' . $bookmark['user']);
                $commentLink = $this->generateLink($this->generateCommentUrl($eid, $bookmark['user']), $bookmark['timestamp']);
                $comment = $this->formatCommnet($bookmark['comment']);
                $comments[] = '<li>[' . $commentLink . '] ' . $userInfo . ' ' . $comment . '</li>';
            }
        }

        return $comments;
    }

    function formatCommnet($comment){
        $comment = htmlspecialchars($comment);
        $comment = $this->linkHttp($comment);
        $comment = $this->linkBookmark($comment);
        $comment = $this->linkTwitter($comment);

        return $comment;
    }

    function generateUserIconUrl($userId)
    {
        return 'https://cdn1.www.st-hatena.com/users/' . substr($userId, 0, 2) . '/' . $userId . '/profile_s.gif';
    }

    function generateUserPageUrl($userId)
    {
        return 'https://b.hatena.ne.jp/' . $userId . '/';
    }

    function generateCommentUrl($eid, $userId)
    {
        return 'https://b.hatena.ne.jp/entry/' . $eid . '/comment/' . $userId;
    }

    function linkHttp($comment){
        return preg_replace('/https?://[\w/:%#\$&\?\(\)~\.=\+\-]+/', $this->generateLink('$0', '$0'), $comment);
    }
    function linkBookmark($comment){
        return preg_replace_callback('/id:([a-zA-Z0-9:-]+)/', function($matches){
            return $this->generateLink('https://b.hatena.ne.jp/' . str_replace(':', '/', $matches[1]), $matches[0]);
        }, $comment);
    }
    function linkTwitter($comment){
        return preg_replace('/(?<=^|(?<=[^a-zA-Z0-9-_\.]))@([A-Za-z]+[A-Za-z0-9_]+)/i', $this->generateLink('https://twitter.com/$1', '$0'), $comment);
    }

    function api_version()
    {
        return 2;
    }

}
