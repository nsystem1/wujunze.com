<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
date_default_timezone_set('PRC');

/**
 * A beautiful and clean WEB Music Player by HTML5. <a href="http://cplayer.js.org/">http://cplayer.js.org/</a>
 * 
 * @package cPlayer
 * @author journey.ad
 * @version 1.2.5
 * @dependence 13.12.12-*
 * @link https://github.com/journey-ad/cPlayer-Typecho-Plugin
 */

class cPlayer_Plugin implements Typecho_Plugin_Interface
{
    //此变量用以在一个变量中区分多个播放器实例
    protected static $playerID = 0;
    protected static $VERSION = '1.2.5';
    protected static $INTEGRITY = 'sha256-k1ocCdwGYdG1lwhqGFhqe5G40e7wm4cbapHtYi+3Rc4='; //commit#016ec4c
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('cPlayer_Plugin','playerparse');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('cPlayer_Plugin','playerparse');
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('cPlayer_Plugin', 'Insert');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('cPlayer_Plugin', 'Insert');
        Typecho_Plugin::factory('Widget_Archive')->header = array('cPlayer_Plugin','playercss');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('cPlayer_Plugin','footerjs');
        $info = self::is_really_writable(dirname(__FILE__)."/cache") ? "插件启用成功！！" : "cPlayer插件目录的cache目录不可写，可能会导致博客加载缓慢！"; 
        return _t($info);
    }


    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        $files = glob('usr/plugins/cPlayer/cache/*');
        foreach($files as $file){
            if (is_file($file)){
                @unlink($file);
            }
        }
        return _t('cPlayer插件禁用成功，所有缓存已清空!');
    }


    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        if (isset($_GET['action']) && $_GET['action'] == 'deletefile')
            self::deletefile();

        $listexpire = new Typecho_Widget_Helper_Form_Element_Text(
            'listexpire', null, '43200',
            _t('歌单更新周期'), _t('设置歌单的缓存时间（单位：秒），超过设定时间后歌单将自动更新'));
        $listexpire->input->setAttribute('placeholder','43200');
        $form->addInput($listexpire);

        $nolyric = new Typecho_Widget_Helper_Form_Element_Text(
            'nolyric', null, '找不到歌词的说…(⊙﹏⊙)',
            _t('找不到歌词时显示的文字'), _t('找不到歌词时显示的文字'));
        $nolyric->input->setAttribute('placeholder','找不到歌词的说…(⊙﹏⊙)');
        $form->addInput($nolyric);

        $notlyric = new Typecho_Widget_Helper_Form_Element_Text(
            'notlyric', null, '翻译不存在的说…╮(╯▽╰)╭',
            _t('翻译不存在时显示的文字'), _t('翻译不存在时显示的文字'));
        $notlyric->input->setAttribute('placeholder','翻译不存在的说…╮(╯▽╰)╭');
        $form->addInput($notlyric);

        $MUSIC_U = new Typecho_Widget_Helper_Form_Element_Text(
            'MUSIC_U', null, '',
            _t('MUSIC_U'), _t('MUSIC_U的值，需要带MUSIC_U='));
        $MUSIC_U->input->setAttribute('placeholder','MUSIC_U=…');
        $form->addInput($MUSIC_U);

        $cache = new Typecho_Widget_Helper_Form_Element_Radio('cache',
            array('false'=>_t('否')),'false',_t('清空缓存'),_t('清空插件生成的缓存文件，必要时可以使用'));
        $form->addInput($cache);

        $submit = new Typecho_Widget_Helper_Form_Element_Submit();
        $submit->value(_t('清空歌词，专辑图片链接，在线歌曲缓存'));
        $submit->setAttribute('style','position:relative;');
        $submit->input->setAttribute('style','position:absolute;bottom:37px;');
        $submit->input->setAttribute('class','btn btn-s btn-warn btn-operate');
        $submit->input->setAttribute('formaction',Typecho_Common::url('/options-plugin.php?config=cPlayer&action=deletefile',Helper::options()->adminUrl));
        $form->addItem($submit);
    }


    /**
     * 缓存清空
     *
     * @access private
     * @return void
     */
    private function deletefile()
    {
        $path = __TYPECHO_ROOT_DIR__ .'/usr/plugins/cPlayer/cache/';

        foreach (glob($path.'*') as $filename) {
            @unlink($filename);
        }

        Typecho_Widget::widget('Widget_Notice')->set(_t('歌词与封面链接，在线歌曲缓存已清空!'),NULL,'success');

        Typecho_Response::getInstance()->goBack();
    }


    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {}


    /**
     * 插件的实现方法
     *
     * @access public
     * @return void
     */
    public static function Insert()
    {
        ?>
        <style>
            li#wmd-music-button{font-size: 20px;line-height: 20px;height: 20px;width: 20px;}#cft-shell{position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:999;}#cft-shell-tips{position:absolute;top:0;height:1.8em;line-height:1.8em;display:block;font-size:1.8em;left:0;margin:0;width:100%;z-index:10;text-align:center;background:#e6efc2;color:#264409;transition:top .2s}#cft-shell-content{position:absolute;top:50%;transform:translate3D(0,-50%,0);-webkit-transform:translate3D(0,-50%,0);width:100%;max-width: 70%;left: 15%;}.media-modal-content{width:auto;margin:0 auto;padding:15px 0;background-color:#f3f3f3}#cft-shell-close{float:right;margin-right:15px;color:#333;width:20px;text-align:center;font-weight:bold}.media-frame-title{padding-left:20px}.media-frame-router{padding-left:20px}.media-router a{display:inline-block;padding:2px 6px;border:1px;border-style:solid solid none;border-radius:2px;border-color:#f3f3f3;outline:0;text-decoration:none}.media-router a.active{display:inline-block;padding:2px 6px;border:1px;border-style:solid solid none;border-radius:2px;border-color:#ccc;outline:0;text-decoration:none;color:#333;background-color:#fefefe}.media-frame-content{padding:20px;background-color:#fefefe;max-height:600px;overflow-y:auto}.cft-ul{list-style:none;padding:0;margin:0}.cft-ul li{display:none;margin:0;padding:0}.cft-ul li.active{display:block;margin:0;padding:0}.cft-li input[type=text]{right:0;width:100%;padding:4px;margin:4px 0}div>label{margin-right:0 4px 0 0}.cft-textarea{width:100%;margin-top:0;margin-bottom:0;height:150px}.media-frame-toolbar{overflow:hidden;margin:20px 20px 0 20px}#cft-shell-insert{float:right}div.media-local-songs{background-color:#eee;padding:10px 5px;margin-bottom:20px;border:1px;border-color:#ccc;border-style:dashed}a.media-local-songs-add{font-size:3em;line-height:1em;color:#666;background-color:#eee;width:100%;margin:.2em 0 0 0;display:inline-block;text-align:center;outline:0;text-decoration:none;transition:.2s}a.media-local-songs-add:hover{background-color:#ccc}#media-toolbar-code{background-color:#fefefe;padding:4px;margin-bottom:10px;height:120px;max-height:120px;width:100%;display:none}media-frame-content::-webkit-scrollbar-track-piece{background:#eee}.media-frame-content::-webkit-scrollbar{width:5px;height:5px}.media-frame-content::-webkit-scrollbar-thumb{height:40px;background-color:#ccc;border-radius:1px}.media-frame-content::-webkit-scrollbar-thumb:hover{background-color:#bbb}
        </style>
        <div id="cft-shell" style="display:none">
            <div id="cft-shell-tips"></div>
            <div id="cft-shell-content" class="media-modal">
                <div class="media-modal-content">
                    <a id="cft-shell-close" class="media-modal-close" href="javascript:void(0);" onclick="return false;">X</a>
                    <div id="cft-shell-body">
                        <div class="media-frame-title">
                            <h2>插入音乐</h2>
                        </div>
                        <div class="media-frame-router">
                            <div class="media-router">
                                <a href="javascript:void(0);" class="media-menu-item" id="media-menu-netease" onclick="return false;">网易云音乐</a>
                                <a href="javascript:void(0);" class="media-menu-item active" id="media-menu-local" onclick="return false;">本地音乐</a>
                            </div>
                        </div>
                        <div class="media-frame-content">
                            <ul class="cft-ul">
                                <li class="cft-li" data-type="netease">
                                    <div>
                                        <label><input type="radio" name="netease_type" value="netease_songlist" checked>单曲</label>
                                        <label><input type="radio" name="netease_type" value="netease_album">专辑</label>
                                        <label><input type="radio" name="netease_type" value="netease_playlist">歌单</label>
                                        <label><input type="radio" name="netease_type" value="netease_artist">艺人</label>
                                        <label><input type="radio" name="netease_type" value="netease_recommend">日推</label>
                                    </div>
                                    <textarea class="cft-textarea large-text code" cols="30" rows="9" placeholder="输入对应ID，单曲每行一个，专辑、歌单、艺人等每次仅能输入一个"></textarea>
                                </li>
                                <li class="cft-li active" data-type="local">
                                    <a class="media-local-songs-add" href="javascript:void(0);" onclick="return false;">+</a>
                                </li>
                            </ul>
                        </div>
                        <div class="media-frame-toolbar">
                            <div class="media-toolbar">
                                <textarea id="media-toolbar-code"></textarea>
                                <div class="media-toolbar-primary">
                                    <button class="btn primary" id="cft-shell-insert">插入至文章</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
        <script type="text/javascript">
        $(function() {
            /* 判断是否为默认编辑器插入音乐按钮 */
            if($('#wmd-button-row').length>0) {
                $('#wmd-button-row').prepend('<li class="wmd-button" id="wmd-music-button" title="插入音乐 Alt + M">♫</li>');
            }else if($('#text-editormd').length>0){
                $('#text-editormd').bind('click', function(e) {
                    $('ul.editormd-menu').prepend('<li><a href="javascript:;" id="wmd-music-button" title="插入音乐 (Alt + M)" unselectable="on"><i class="fa fa-music" name="music" unselectable="on"></i></a></li>');
                    if($('ul.editormd-menu li a#wmd-music-button').length > 0) $(this).unbind(e);
                });
            }
            else {
                $('#text').before('<a id="wmd-music-button" title="插入音乐 Alt + M" href="javascript:void(0)" onclick="return false;">插入歌曲</a>');
            }
            /* 为编辑器按钮增加点击事件 */
            $(document).on('click', '#wmd-music-button', function() {
                $('#cft-shell').show();
            });
            $(document).on('click', '#cft-shell-close', function() {
                $('#cft-shell').hide();
            });
            
            /* 增加各种快捷键操作 */
            $(document).on('keydown', function(e){  
                
                /* Alt+M 调出插件面板 */
                if(e.altKey && e.keyCode == '77') 
                    $('#wmd-music-button').click();

                /* ESC 关闭插件面板 */
                if(e.keyCode == '27')
                    $('#cft-shell-close').click();
            })

            $(document).ready(function(){
                var $div = $("div#cft-shell-content");
                $div.bind("mousedown",function(event){
                    $div.css('cursor', 'move');
                    var offset_x = $(this)[0].offsetLeft;
                    var offset_y = $(this)[0].offsetTop;
                    var mouse_x = event.pageX;
                    var mouse_y = event.pageY;
                    $("#cft-shell-body").bind("mousemove", function(e){
                        $(this).css('cursor', 'auto');
                        if (e.stopPropagation) e.stopPropagation();
                            else e.cancelBubble = true;
                    });
                    $(document).bind("mousemove",function(ev){
                        var _x = ev.pageX - mouse_x;
                        var _y = ev.pageY - mouse_y;
                        var now_x = (offset_x + _x ) + "px";
                        var now_y = (offset_y + _y ) + "px";
                        $div.css({
                            top:now_y,
                            left:now_x
                        });
                    });
                });
                $(document).bind("mouseup",function(){
                    $(this).unbind("mousemove");
                    $div.css('cursor', 'auto');
                });
            })

            $(document).on('click', '#media-menu-netease', function() {
                $('#media-menu-local').removeClass('active');
                $('.cft-li[data-type=local]').removeClass('active');
                $('#media-menu-netease').addClass('active');
                $('.cft-li[data-type=netease]').addClass('active');
            });
            $(document).on('click', '#media-menu-local', function() {
                $('#media-menu-netease').removeClass('active');
                $('.cft-li[data-type=netease]').removeClass('active');
                $('#media-menu-local').addClass('active');
                $('.cft-li[data-type=local]').addClass('active');
            });
            
            $(document).on('click', '#cft-shell-insert', function() {
                switch ($('.media-router').children().filter('.active')[0].id){
                    case 'media-menu-netease':
                        grin(parse_netease());
                        break;
                    case 'media-menu-local':
                        grin(parse_local());
                        break;
                    default:
                };
            });
            var songs_div = `
<div class="media-local-songs">
    <a href="javascript:void(0);" style="float: right;color: #333;" onclick="$(this).parent().remove();return false;">X</a>
    歌曲链接：<input type="text" name="song_url" placeholder="http://…">
    标题：<input type="text" name="song_name" placeholder="好久不见" style="width: 180px">
    艺术家：<input type="text" name="song_artist" placeholder="陈奕迅" style="width: 180px"><br>
    歌词：
    <textarea class="cft-textarea large-text lyric" cols="30" rows="9" placeholder="请输入lrc格式的歌词文本或歌词链接"></textarea><br>
    歌词翻译：
    <textarea class="cft-textarea large-text tlyric" cols="30" rows="9" placeholder="请输入lrc格式的歌词翻译文本或歌词翻译链接"></textarea>
</div>
`;          
            $(document).on('click', 'a.media-local-songs-add', function() {
                $(songs_div).insertBefore('a.media-local-songs-add');
                $(".media-frame-content").animate({scrollTop:$(".media-frame-content")[0].scrollHeight}, 350,'linear');
            })
            $('a.media-local-songs-add').click();
        })
        $('#cft-shell-tips').text('请在下方填写相关内容').delay(3000).slideToggle();
        function parse_netease(){
            switch ($("input[name='netease_type']:checked").val()){
                case 'netease_songlist':
                    var songs = $('.cft-textarea').val().split('\n').toString();
                    var code = "[player id='" + songs + "'/]\n"
                    break;
                case 'netease_album':
                    var album = $('.cft-textarea').val().toString();
                    var code = "[player id='" + album + "' type='album'/]\n"
                    break;
                case 'netease_playlist':
                    var playlist = $('.cft-textarea').val().toString();
                    var code = "[player id='" + playlist + "' type='collect'/]\n"
                    break;
                case 'netease_artist':
                    var artist = $('.cft-textarea').val().toString();
                    var code = "[player id='" + artist + "' type='artist'/]\n"
                    break;
                case 'netease_recommend':
                    var code = "[player type='recommend'/]\n";
                    break;
                default:                
            }
            return code;
        }
        function parse_local(){
            var code_player = `[player]\n{mp3}[/player]\n`;
            var code_mp3 = `[mp3 url="{url}" artist="{artist}" name="{name}" {else}]\n[lrc]\n{lrc}\n[/lrc]\n[tlrc]\n{tlrc}\n[/tlrc]\n[/mp3]\n`;
            var mp3 = '';
            for(var i=0; i < $('.cft-li[data-type=local]').children().filter('.media-local-songs').length; i++){
                var dom = $('.cft-li[data-type=local]').children().filter('.media-local-songs')[i];
                var song = new Object();
                
                song.url =  $(dom).children().filter('input[name=song_url]').val();
                song.name =  $(dom).children().filter('input[name=song_name]').val();
                song.artist =  $(dom).children().filter('input[name=song_artist]').val();
                song.lrc =  $(dom).children().filter('textarea.lyric').val();
                song.tlrc =  $(dom).children().filter('textarea.tlyric').val();
                
                _temp = code_mp3.replace('{url}', song.url);
                _temp = _temp.replace('{artist}', song.artist);
                _temp = _temp.replace('{name}', song.name);
                
                _temp2 = '';
                if (isURL(song.lrc)){
                    _temp2 += 'lrc="' + song.lrc + '" ';
                    _temp = _temp.replace('[lrc]\n{lrc}\n[/lrc]\n', '');
                }else _temp = _temp.replace('{lrc}', song.lrc);

                if (isURL(song.tlrc)){
                    _temp2 += 'tlrc="' + song.tlrc + '" ';
                    _temp = _temp.replace('[tlrc]\n{tlrc}\n[/tlrc]\n', '');
                }else _temp = _temp.replace('{tlrc}', song.tlrc);
                
                _temp = _temp.replace('{else}', _temp2);
                mp3 += _temp;
            }
            var player = code_player.replace('{mp3}', mp3)
            return player;
            
        }
        
        function isURL(str){
            return !!str.match(/((http|ftp|https):\/\/([\w\-]+\.)+[\w\-]+(\/[\w\u4e00-\u9fa5\-\.\/?\@\%\!\&=\+\~\:\#\;\,]*)?)/ig);
        }
        
        function grin(tag) {
            var myField;
            if ($('textarea#text').css('display') == 'none'){
                $('#media-toolbar-code').val(tag);
                $('#media-toolbar-code').show();
                $('#cft-shell-tips').text('当前编辑器不支持自动插入，请手动复制').slideToggle().delay(5000).slideToggle();
            } else {
                myField = document.getElementById('text');
                if (document.selection) {
                    myField.focus();
                    sel = document.selection.createRange();
                    sel.text = tag;
                    myField.focus();
                }else if (myField.selectionStart || myField.selectionStart == '0') {
                    var startPos = myField.selectionStart;
                    var endPos = myField.selectionEnd;
                    var cursorPos = startPos;
                    myField.value = myField.value.substring(0, startPos)
                    + tag
                    + myField.value.substring(endPos, myField.value.length);
                    cursorPos += tag.length;
                    myField.focus();
                    myField.selectionStart = cursorPos;
                    myField.selectionEnd = cursorPos;
                } else {
                    myField.value += tag;
                    myField.focus();
                }
                $('#cft-shell-tips').text('代码已插入至文章内光标所在位置').slideToggle().delay(5000).slideToggle()
            }
        }
        </script>

        <?php
    }


    /**
     * 头部css挂载,并定义参数的变量
     * 
     * @return void
     */
    public static function playercss()
    {
        $playerurl = Helper::options()->pluginUrl.'/cPlayer/assets/dist/';
        $VERSION = self::$VERSION;
        echo <<<EOF
<!-- cPlayer Start -->
<link rel="stylesheet" type="text/css" href="{$playerurl}cplayer.min.css?v={$VERSION}" />
<script>var cPlayers = [];var cPlayerOptions = [];</script>
<!-- cPlayer End -->
EOF;
    }


    /**
     * 尾部js，解析文章中给header的播放器变量添加的播放器参数并生成播放器的html
     *
     *
     * @return void
     */
    public static function footerjs()
    {
        $playerurl = Helper::options()->pluginUrl.'/cPlayer/assets/dist/';
        $VERSION = self::$VERSION;
        $INTEGRITY = self::$INTEGRITY;
        echo <<<EOF
<!-- cPlayer Start -->
<script async>
"use strict";
(function(){
var cp = function(){
    var len = cPlayerOptions.length;
    for(var i=0;i<len;i++){
        var element = document.getElementById('player' + cPlayerOptions[i]['id'])
        while (element.hasChildNodes()) {
            element.removeChild(element.firstChild);
        };
        cPlayers[i] = new cPlayer({
            element: element,
            list: cPlayerOptions[i]['list'],
            });
    };
    cPlayers = [];cPlayerOptions = [];
};
var script = document.createElement('script');
script.type = "text/javascript";
script.src = "{$playerurl}cplayer.min.js?v={$VERSION}";
script.async = true;
script.crossOrigin = "anonymous";
script.integrity = "{$INTEGRITY}";
if(script.readyState){  //IE
    script.onreadystatechange = function(){
        if (script.readyState == "loaded" ||
            script.readyState == "complete"){
            script.onreadystatechange = null;
            cp();
        }
    };
}else{  //Others
    script.onload = function(){
        cp();
    };
}
document.head.appendChild(script);
})();
</script>
<!-- cPlayer End -->
EOF;
    }


    /**
     * 内容标签替换
     * 
     * @param string $content
     * @return string
     */
    public static function playerparse($content,$widget,$lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;

        if ($widget instanceof Widget_Archive) {

            //当没有标签时候就直接return提高运行效率
            if ( false === strpos( $content, '[' ) ) {
                return $content;
            }

            $pattern = self::get_shortcode_regex( array('player') );
            $content = preg_replace_callback("/$pattern/",array('cPlayer_Plugin','parseCallback'), $content);

        }

        return $content;
    }


    /**
     * 回调解析
     * @param unknown $matches
     * @return string
     */
    public static function parseCallback($matches)
    {
        /*
            $mathes array
            * 1 - An extra [ to allow for escaping shortcodes with double [[]]
            * 2 - The shortcode name
            * 3 - The shortcode argument list
            * 4 - The self closing /
            * 5 - The content of a shortcode when it wraps some content.
            * 6 - An extra ] to allow for escaping shortcodes with double [[]]
         */

        // allow [[player]] syntax for escaping the tag
        if ( $matches[1] == '[' && $matches[6] == ']' ) {
            return substr($matches[0], 1, -1);
        }
        //播放器id
        $id = self::getUniqueId();
        //还原转义后的html
        //[player title=&quot;Test Abc&quot; artist=&quot;haha&quot; id=&quot;1234543&quot;/]
        $attr = htmlspecialchars_decode($matches[3]);
        //[player]标签的属性，类型为array
        $atts = self::shortcode_parse_atts($attr);
        //开始解析音乐地址
        $result = array();
        //解析[player]标签内id和url属性
        if (isset($atts['url']) || isset($atts['id']) || isset($atts['type'])){
            $r = self::parse($matches[5], $atts);
            if ($r) $result = array_merge($result, $r);
        }
        //解析[player][/player]内部的[mp3]标签
        if ($matches[4] != '/' && $matches[5]){
            //获取正则
            $regex = self::get_shortcode_regex(array('mp3'));
            //过滤html标签并还原转义后的字符
            $content = htmlspecialchars_decode(strip_tags($matches[5]));
            //开始解析
            if ( ( false !== strpos( $content, '[' ) ) && preg_match_all("/$regex/", $content , $all)){
                foreach ($all[0] as $k=>$v){
                    $a = self::shortcode_parse_atts($all[3][$k]);
                    //获取所有music信息
                    $r = self::parse(trim($all[5][$k]), $a);
                    if ($r) $result = array_merge($result, $r);
                }
            }
        }
        //删除id避免与后面的id属性冲突
        if (isset($atts['id'])) unset($atts['id']);
        //没有歌曲时候直接返回空值避免出错
        if (empty($result)) return '';
        //播放器默认属性
        $data = array(
            'id' => $id
        );
        //设置播放器属性
        if(!empty($atts)){
            foreach ($atts as $k => $att) {
                $data[$k] = $atts[$k];
            }
        }
        //输出代码
        $playerCode =  '<div id="player'.$id.'" class="cPlayer">
        ';

        //nolyric
        $nolyric = Typecho_Widget::widget('Widget_Options')->plugin('cPlayer')->nolyric;
        if (!$nolyric) $nolyric = '找不到歌词的说…(⊙﹏⊙)';

        //notlyric
        $notlyric = Typecho_Widget::widget('Widget_Options')->plugin('cPlayer')->notlyric;
        if (!$notlyric) $notlyric = '翻译不存在的说…╮(╯▽╰)╭';

        //歌词
        if (!empty($result)){
            foreach ($result as $k=>$v){
                //歌词不存在的时候输出'no lyric'
                $result[$k]['lyric'] = $v['lyric'] ? $v['lyric'] : "[00:00.00]$nolyric\n[99:00.00] ";
                $result[$k]['transLyric'] = $v['transLyric'] ? $v['transLyric'] : "[00:00.00]$notlyric\n[99:00.00] ";
            }
        }
        $playerCode .= "</div>\n";
        //开始添加歌曲列表
        $data['list'] = $result;
        //加入头部数组
        $js = json_encode($data);
        $playerCode .= <<<EOF
<script>cPlayerOptions.push({$js});</script>
EOF;

        return $playerCode;
    }


    /**
     * 获取一个唯一的id以区分各个播放器实例
     * @return number
     */
    public static function getUniqueId()
    {
        self::$playerID++;
        return self::$playerID;
    }


    /**
     * 根据参数进一步解析得到歌曲的信息
     * 
     * @param string $content 标签内的内容，如歌词
     * @param array $atts 歌曲的属性
     * @return array 包含解析结果的数组
     */
    private static function parse($content = '',$atts = array())
    {
        //过滤html标签避免出错
        $content = strip_tags($content);
        //取出[lrc]
        $lyric = false;
        if( preg_match('/\[(lrc)](.*?)\[\/\\1]/si', $content ,$lyrics) ){
            $lyric = $lyrics[2];
        }
        //取出[tlrc]
        $tlyric = false;
        if( preg_match('/\[(tlrc)](.*?)\[\/\\1]/si', $content ,$tlyrics) ){
            $tlyric = $tlyrics[2];
        }
        //最终结果
        $return = array();
        //解析歌词，如果没有[lrc][/lrc]文本歌词但是有lrc的url的话直接从url中读取并缓存
        if(isset($atts['lrc']) && !$lyric){
            if($c = self::getlrc($atts['lrc']))
                $lyric = $c;
        }
        $atts['lyric'] = false;
        //解析歌词，如果没有[tlrc][/tlrc]文本歌词但是有tlrc的url的话直接从url中读取并缓存
        if(isset($atts['tlrc']) && !$tlyric){
            if($c = self::getlrc($atts['tlrc']))
                $tlyric = $c;
        }
        $atts['transLyric'] = false;
        //解析网易云音乐
        if(isset($atts['id']) || isset($atts['type'])){
            $id = isset($atts['id']) ? $atts['id'] : null;
            $type = isset($atts['type']) ? $atts['type'] : 'song';
            $result = self::parse_netease($id, $type);
            if ($result)
                $return = array_merge($return, $result);
        }
        //当网易只返回了一首歌或是插入自己上传的音乐才考虑下方情况
        if (isset($atts['url']) || count($return) === 1) {
            //自定义歌词
            if($lyric)
                $atts['lyric'] = $lyric;
            if($tlyric)
                $atts['transLyric'] = $tlyric;
            //解析封面
            if(( ! isset($atts['id']) && isset($atts['url']) && !isset($atts['cover'])) || (isset($atts['cover']) && $atts['cover'] == 'search')){
                $name = isset($atts['name']) ? $atts['name'] : '';
                $artist = isset($atts['artist']) ? $atts['artist'] : '';
                $words = $name.' '.$artist;
                if ($name || $artist) {
                    if ($p = self::getcover($words)) {
                        $atts['cover'] = $p;
                    }elseif ($artist){
                        if ($p = self::getcover($artist)){
                            $atts['cover'] = $p;
                        }
                    }
                }
            }
            //标题和艺术家
            if (! isset($atts['artist']) && ! isset($atts['id']) && isset($atts['url'])) {
                $atts['artist'] = 'Unknown';
            }
            if (! isset($atts['name']) && ! isset($atts['id']) && isset($atts['url'])) {
                $atts['name'] = 'Unknown';
            }
            //假如不要自动查找封面的话
            if (isset($atts['cover'])){
                if ($atts['cover'] == 'false' || !(bool)$atts['cover'])
                    $atts['cover'] = '';
                $atts['image'] = $atts['cover'];
            }
            //判断是修改网易获取的歌曲属性还是添加自己的歌曲链接
            if (!isset($atts['id']) && !isset($atts['type'])) {
                $return[] = $atts;
            }else{
                //当没有自定义歌词时候删除变量避免覆盖掉原有歌词
                if ( ! $atts['lyric']) {
                    unset($atts['lyric']);
                }
                if ( ! $atts['transLyric']) {
                    unset($atts['transLyric']);
                }
                $return[0] = array_merge($return[0], $atts);
            }
        }
        return $return;
    }


    /**
     * 解析netease信息
     * 
     * @param unknown $id
     * @param unknown $type
     * @return boolean|multitype:multitype:unknown Ambigous <>
     */
    private static function parse_netease($id=null, $type='song')
    {
        //当id过长时md5避免缓存出错
        $key = 'netease_'.$type.'_'.(strlen($id) > 20 ? md5($id) : $id);
        $result = self::cache_get($key);
        //列表更新周期
        $listexpire = Typecho_Widget::widget('Widget_Options')->plugin('cPlayer')->listexpire;
        if ($listexpire === null) $listexpire = 43200;
        $listexpire = (int)$listexpire;

        //若类型为日推且当前时间为缓存时间第二天6:00之后则重新请求
        if($result && isset($result['data']) && $type == "recommend"){
            if((date("m", time() > date("m", $result['time']))) || (date("d", time()) > date("d", $result['time']) && date("Hi", time()) > 600)){
                $data = self::get_netease_music($id, $type);
                self::cache_set($key, array('time' => time(),'data' => $data));
            }else $data = $result['data'];
        //缓存过期或者找不到的时候则重新请求服务器（设置过期时间是因为歌单等信息可能会发生改变），否则返回缓存
        }elseif ($result && isset($result['data']) && ($type == "song" || (isset($result['time']) && (time() - $result['time']) < $listexpire))){
            $data = $result['data'];
        }else{
            $data = self::get_netease_music($id, $type);
            self::cache_set($key, array('time' => time(),'data' => $data));
        }

        if (empty($data['trackList'])) return false;
        $return = array();
        foreach ($data['trackList'] as $v){
            $return[] = array(
                'artist' => $v['artist'],
                'name' => $v['title'],
                'image' => $v['pic'],
                'url' => $v['location'],
                'lyric' => $v['lyric'],
                'transLyric' => $v['tlyric'],
            );
        }
        return $return; 

    }


    /**
     * 从netease中获取歌曲信息
     * 
     * @link https://github.com/webjyh/WP-Player/blob/master/include/player.php
     * @param unknown $id 
     * @param unknown $type 获取的id的类型，song:歌曲,album:专辑,artist:艺人,collect:歌单,recommend:日推
     */
    private static function get_netease_music($id=null, $type = 'song')
    {
        $return = false;
        $data = array(
            'COOKIE' => 'appver=2.0.2;',
            'REFERER' => 'http://music.163.com/',
            'USERAGENT' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.157 Safari/537.36'
        );
        switch ( $type ) {
            case 'song': $url = "http://music.163.com/api/song/detail/?ids=[$id]"; $key = 'songs'; break;
            case 'album': $url = "http://music.163.com/api/album/$id?id=$id"; $key = 'album'; break;
            case 'artist': $url = "http://music.163.com/api/artist/$id?id=$id"; $key = 'artist'; break;
            case 'collect': $url = "http://music.163.com/api/playlist/detail?id=$id"; $key = 'result'; break;
            case 'recommend': 
                $url = "http://music.163.com/api/discovery/recommend/songs";
                $MUSIC_U = Typecho_Widget::widget('Widget_Options')->plugin('cPlayer')->MUSIC_U;
                $data['COOKIE'] .= $MUSIC_U;

                $key = 'recommend';
                break;
            default: $url = "http://music.163.com/api/song/detail/?ids=[$id]"; $key = 'songs';
        }
        $cexecute = self::fetch_url($url, $data);
        if ( $cexecute ) {
            $result = json_decode($cexecute, true);
            if ( $result['code'] == 200 && $result[$key] ){
                $return['status'] = true;
                $return['message'] = "";

                switch ( $key ){
                    case 'songs' : $data = $result[$key]; break;
                    case 'album' : $data = $result[$key]['songs']; break;
                    case 'artist' : $data = $result['hotSongs']; break;
                    case 'result' : $data = $result[$key]['tracks']; break;
                    case 'recommend' : $data = $result[$key]; break;
                    default : $data = $result[$key]; break;
                }

                //列表
                $list = array();
                foreach ( $data as $keys => $data ){
                    //获取歌词
                    $lyric = self::get_netease_lyric($data['id']);

                    $list[$data['id']] = array(
                        'song_id' => $data['id'],
                        'title' => $data['name'],
                        'album_name' => $data['album']['name'],
                        'artist' => $data['artists'][0]['name'],
                        'location' => str_replace('http://m', '//p', $data['mp3Url']),
                        'pic' => str_replace('http://p', '//p', $data['album']['blurPicUrl'].'?param=128x128'),
                        'lyric' => $lyric['lyric'],
                        'tlyric' => $lyric['tlyric']
                    );
                }
                //修复一次添加多个id的乱序问题
                if ($type = 'song' && strpos($id, ',')) {
                    $ids = explode(',', $id);
                    $r = array();
                    foreach ($ids as $v) {
                        if (!empty($list[$v])) {
                            $r[] = $list[$v];
                        }
                    }
                    $list = $r;
                }
                //最终播放列表
                $return['trackList'] = $list;
            }
        } else {
            $return = array('status' =>  false, 'message' =>  '非法请求');
        }
        return $return;
    }


    /**
     * 根据id从netease中获取歌词，带缓存
     */
    private static function get_netease_lyric($id)
    {
        $key = 'netease_lrc_'.$id;
        $result = self::cache_get($key);
        if($result && isset($result[0])){
            return $result[0];
        }else{
            //缓存取不到则重新抓取
            $url = "http://music.163.com/api/song/lyric?os=pc&id=$id&lv=-1&kv=-1&tv=-1";
            if (!function_exists('curl_init') ) {
                return false;
            } else {
                $data = array(
                    'COOKIE' => 'appver=2.0.2',
                    'REFERER' => 'http://music.163.com/'
                );
                $cexecute = self::fetch_url($url, $data);
                
                $JSON = false;
                if ( $cexecute ) {
                    $result = json_decode($cexecute, true);
                    if ( $result['code'] == 200 && isset($result['lrc']['lyric']) && $result['lrc']['lyric'] ){
                        $JSON = array(
                            'status' => true,
                            'lyric' => $result['lrc']['lyric'],
                            'tlyric' => $result['tlyric']['lyric']
                        );
                    }
                } else {
                    $JSON = array('status' => true, 'lyric' => null, 'tlyric' => null);
                }
                //存入缓存
                self::cache_set($key, array($JSON));
                return $JSON;
            }
        }
    }


    /**
     * 通过关键词从豆瓣获取专辑封面链接，当缓存存在时则直接读取缓存
     * 
     * @param string $words
     * @return boolean|string
     */
    private static function getcover($words)
    {

        $key = 'cover_'.md5($words);

        if($g = self::cache_get($key)){
            if(!isset($g[0])) return false;
            return $g[0];
        }else{
            //缓存不存在时用豆瓣获取并存入缓存
            $arg = http_build_query(array('q' => $words,'count'=> 1 ));
            $url = false;
            $g = self::fetch_url('https://api.douban.com/v2/music/search?'.$arg);

            if ($g){
                $g = json_decode($g,true);
                if($g['count']){
                    $url = $g['musics'][0]['image'];
                    //换成大图
                    $url = str_replace("/spic/", "/mpic/", $url);
                }
            }
            //用array包裹这个变量就不会判断错误啦
            self::cache_set($key,array($url));
            return $url;

        }

    }


    /**
     * 通过url获取歌词内容，若缓存存在就直接读取缓存
     * 
     * @param string $url
     * @return boolean|string
     */
    private static function getlrc($url)
    {
        $key = 'lrc_'.md5($url);
        if($g = self::cache_get($key)){
            if(!isset($g[0])) return false;
            return $g[0];
        }else{
            //缓存不存在时用url获取并存入缓存
            $lyric = self::fetch_url($url);
            //用array包裹这个变量就不会判断错误啦
            self::cache_set($key,array($lyric));
            return $lyric;
        }
        
    }


    /**
     * 缓存写入
     * 
     * @param unknown $key
     * @param unknown $value
     * @return number
     */
    private static function cache_set($key, $value)
    {
        $cachedir = dirname(__FILE__)."/cache";

        $fp = fopen($cachedir.'/'.$key,"w+");
        $status = fwrite($fp,serialize($value));
        fclose($fp);
        return $status;
    }


    /**
     * 缓存读取
     * 
     * @param unknown $key
     * @return mixed|boolean
     */
    private static function cache_get($key)
    {
        $cachedir = dirname(__FILE__)."/cache";

        //找到缓存直接读取缓存目录的文件
        if(file_exists($cachedir.'/'.$key)){
            return unserialize(file_get_contents($cachedir.'/'.$key));
        }else{
            return false;
        }
    }


    /**
     * url抓取,两种方式,优先用curl,当主机不支持curl时候采用file_get_contents
     * 参数$data为数组，结构类似于
     * $data = array(
            'POST'       => '',
            'COOKIE'     => 'appver=2.0.2',
            'REFERER'    => 'http://music.163.com/',
            'HTTPHEADER' => '',
            'USERAGENT'  => ''
        );
     * 
     * @param unknown $url
     * @param array $data
     * @return boolean|mixed
     */
    private static function fetch_url($url,$data = null)
    {
        if(function_exists('curl_init')){
            $curl=curl_init();
            curl_setopt($curl,CURLOPT_URL,$url);
            if(isset($data['POST'])){
                if(is_array($data['POST'])) $data['POST'] = http_build_query($data['POST']);
                curl_setopt($curl,CURLOPT_POSTFIELDS,$data['POST']);
                curl_setopt($curl,CURLOPT_POST, true);
            }
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            if(isset($data['HTTPHEADER'])) curl_setopt($curl, CURLOPT_HTTPHEADER, $data['HTTPHEADER']);
            if(isset($data['REFERER'])) curl_setopt($curl,CURLOPT_REFERER, $data['REFERER']);
            if(isset($data['COOKIE'])) curl_setopt($curl,CURLOPT_COOKIE, $data['COOKIE']);
            if(isset($data['USERAGENT'])) curl_setopt($curl,CURLOPT_USERAGENT, $data['USERAGENT']);
            $result=curl_exec($curl);
            $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($httpCode != 200) return false;
            return $result;
        }else{
            //若主机不支持openssl则file_get_contents不能打开https的url
            if($result = @file_get_contents($url)){
                if (strpos($http_response_header[0],'200')){
                    return $result;
                }
            }
            return false;
        }
    }


    /**
     * Retrieve all attributes from the shortcodes tag.
     *
     * The attributes list has the attribute name as the key and the value of the
     * attribute as the value in the key/value pair. This allows for easier
     * retrieval of the attributes, since all attributes have to be known.
     *
     * @link https://github.com/WordPress/WordPress/blob/master/wp-includes/shortcodes.php
     * @since 2.5.0
     *
     * @param string $text
     * @return array|string List of attribute values.
     *                      Returns empty array if trim( $text ) == '""'.
     *                      Returns empty string if trim( $text ) == ''.
     *                      All other matches are checked for not empty().
     */
    private static function shortcode_parse_atts($text)
    {
        $atts = array();
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);
        if ( preg_match_all($pattern, $text, $match, PREG_SET_ORDER) ) {
            foreach ($match as $m) {
                if (!empty($m[1]))
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                elseif (!empty($m[3]))
                $atts[strtolower($m[3])] = stripcslashes($m[4]);
                elseif (!empty($m[5]))
                $atts[strtolower($m[5])] = stripcslashes($m[6]);
                elseif (isset($m[7]) && strlen($m[7]))
                $atts[] = stripcslashes($m[7]);
                elseif (isset($m[8]))
                $atts[] = stripcslashes($m[8]);
            }
    
            // Reject any unclosed HTML elements
            foreach( $atts as &$value ) {
                if ( false !== strpos( $value, '<' ) ) {
                    if ( 1 !== preg_match( '/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value ) ) {
                        $value = '';
                    }
                }
            }
        } else {
            $atts = ltrim($text);
        }
        return $atts;
    }


    /**
     * Retrieve the shortcode regular expression for searching.
     *
     * The regular expression combines the shortcode tags in the regular expression
     * in a regex class.
     *
     * The regular expression contains 6 different sub matches to help with parsing.
     *
     * 1 - An extra [ to allow for escaping shortcodes with double [[]]
     * 2 - The shortcode name
     * 3 - The shortcode argument list
     * 4 - The self closing /
     * 5 - The content of a shortcode when it wraps some content.
     * 6 - An extra ] to allow for escaping shortcodes with double [[]]
     *
     * @link https://github.com/WordPress/WordPress/blob/master/wp-includes/shortcodes.php
     * @since 2.5.0
     *
     *
     * @param array $tagnames List of shortcodes to find. Optional. Defaults to all registered shortcodes.
     * @return string The shortcode search regular expression
     */
    private static function get_shortcode_regex( $tagnames = null )
    {
        $tagregexp = join( '|', array_map('preg_quote', $tagnames) );
    
        // WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcode_tag()
        // Also, see shortcode_unautop() and shortcode.js.
        return
        '\\['                              // Opening bracket
        . '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
        . "($tagregexp)"                     // 2: Shortcode name
        . '(?![\\w-])'                       // Not followed by word character or hyphen
        . '('                                // 3: Unroll the loop: Inside the opening shortcode tag
        .     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
        .     '(?:'
        .         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
        .         '[^\\]\\/]*'               // Not a closing bracket or forward slash
        .     ')*?'
        . ')'
        . '(?:'
        .     '(\\/)'                        // 4: Self closing tag ...
        .     '\\]'                          // ... and closing bracket
        . '|'
        .     '\\]'                          // Closing bracket
        .     '(?:'
        .         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
        .             '[^\\[]*+'             // Not an opening bracket
        .             '(?:'
        . '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
        .                 '[^\\[]*+'         // Not an opening bracket
        .             ')*+'
        .         ')'
        .         '\\[\\/\\2\\]'             // Closing shortcode tag
        .     ')?'
        . ')'
        . '(\\]?)';                          // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
    }


    /**
     * Tests for file writability
     *
     * is_writable() returns TRUE on Windows servers when you really can't write to
     * the file, based on the read-only attribute. is_writable() is also unreliable
     * on Unix servers if safe_mode is on.
     *
     * @link    https://bugs.php.net/bug.php?id=54709
     * @param   string
     * @return  bool
     */
    private static function is_really_writable($file)
    {
        // Create cache directory if not exists
        if (!file_exists($file))
        {
            mkdir($file, 0755);
        }
        // If we're on a Unix server with safe_mode off we call is_writable
        if (DIRECTORY_SEPARATOR === '/' && (version_compare(PHP_VERSION, '5.4', '>=') OR ! ini_get('safe_mode')))
        {
            return is_writable($file);
        }
        /* For Windows servers and safe_mode "on" installations we'll actually
         * write a file then read it. Bah...
         */
        if (is_dir($file))
        {
            $file = rtrim($file, '/').'/'.md5(mt_rand());
            if (($fp = @fopen($file, 'ab')) === FALSE)
            {
                return FALSE;
            }
            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return TRUE;
        }
        elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE)
        {
            return FALSE;
        }
        fclose($fp);
        return TRUE;
    }
}
