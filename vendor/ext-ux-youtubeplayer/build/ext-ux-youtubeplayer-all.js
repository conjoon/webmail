Ext.namespace("Ext.ux.YoutubePlayer");Ext.ux.YoutubePlayer=Ext.extend(Ext.FlashComponent,{player:null,videoId:null,initComponent:function(){this.addEvents("ready","stateChange","error");Ext.apply(this,{ratioMode:this.ratioMode||"normal",id:this.playerId,swfId:this.playerId,style:this.ratioMode=="strict"?"position:relative":"position:static"});Ext.applyIf(this,{url:"http://gdata.youtube.com/apiplayer?key="+this.developerKey+"&enablejsapi=1&playerapiid="+this.playerId,start:false,controls:false,cls:"ext-ux-youtubeplayer "+this.ratioMode,scripting:"always",params:{wmode:"opaque",bgcolor:this.bgColor||"#cccccc"}});if(!Ext.ux.YoutubePlayer.Players){Ext.ux.YoutubePlayer.Players=[]}Ext.ux.YoutubePlayer.Players[this.playerId]=this},_initPlayer:function(){this.player=this.swf},_delegateStateEvent:function(A){switch(A){case -1:A="unstarted";break;case 0:A="ended";break;case 1:A="playing";break;case 2:A="paused";break;case 3:A="buffering";break;case 5:A="video_cued";break;default:A="unknown";break}this.fireEvent("stateChange",A,this,this.player)},_delegateErrorEvent:function(A){switch(A){case 100:A="video_not_found";break;default:A="unknown";break}this.fireEvent("error",A,this,this.player)},onResize:function(B,D,A,C){if(this.playerAvailable()){this.adjustRatio(this.getWidth(),this.getHeight())}},adjustRatio:function(D,B){var A=this.player.style;switch(this.ratioMode){case"strict":if(D<400||B<320){var C=Math.floor(D*0.8);if(C>B){D=Math.floor(B/0.8)}else{B=C}}else{if(B>320){B=320;D=400}}A.marginTop=-Math.floor(B/2)+"px";A.marginLeft=-Math.floor(D/2)+"px";A.height=B+"px";A.width=D+"px";A.top="50%";A.left="50%";this.setPlayerSize(D,B);break;case"stretch":A.margin="auto";A.height=B+"px";A.width=D+"px";this.setPlayerSize(D,B);break}},playerAvailable:function(){return(this.player&&this.player.getPlayerState)?true:false},loadVideoById:function(A,B){this.player.loadVideoById(A,B);this.videoId=A},cueVideoById:function(A,B){this.player.cueVideoById(A,B);this.videoId=A},setPlayerSize:function(B,A){if(!this.playerAvailable()){return }this.player.setSize(B,A)},playVideo:function(){if(!this.playerAvailable()){return }this.player.playVideo()},pauseVideo:function(){if(!this.playerAvailable()){return }this.player.pauseVideo()},stopVideo:function(){if(!this.playerAvailable()){return }this.player.stopVideo()},clearVideo:function(){if(!this.playerAvailable()){return }this.videoId=null;this.player.clearVideo()},getVideoBytesLoaded:function(){if(!this.playerAvailable()){return 0}return this.player.getVideoBytesLoaded()},getVideoBytesTotal:function(){if(!this.playerAvailable()){return 0}return this.player.getVideoBytesTotal()},getVideoStartBytes:function(){if(!this.playerAvailable()){return 0}return this.player.getVideoStartBytes()},mute:function(A){if(!this.playerAvailable()){return }if(A===false){this.player.unMute();this.setVolume(this.getVolume())}else{this.player.mute()}},isMuted:function(A){if(!this.playerAvailable()){return true}return this.player.isMuted()},setVolume:function(A){if(!this.playerAvailable()){return }this.player.setVolume(A)},getVolume:function(){if(!this.playerAvailable()){return 0}return this.player.getVolume()},seekTo:function(B,A){if(!this.playerAvailable()){return }this.player.seekTo(B,A)},getPlayerState:function(){var A=-9999;if(!this.playerAvailable()){return }else{A=this.player.getPlayerState()}switch(A){case -1:A="unstarted";break;case 0:A="ended";break;case 1:A="playing";break;case 2:A="paused";break;case 3:A="buffering";break;case 5:A="video_cued";break;default:A="unknown";break}return A},getCurrentTime:function(){if(!this.playerAvailable()){return 0}return this.player.getCurrentTime()},getDuration:function(){if(!this.playerAvailable()){return 0}return this.player.getDuration()},getVideoUrl:function(){if(!this.playerAvailable()){return""}return this.player.getVideoUrl()},getVideoEmbedCode:function(){if(!this.playerAvailable()){return""}return this.player.getVideoEmbedCode()}});var _onYouTubePlayerReady=function(B){var A=Ext.ux.YoutubePlayer.Players[B];if(A){var C=document.getElementById(B);A._initPlayer();C.addEventListener("onStateChange","Ext.ux.YoutubePlayer.Players['"+B+"']._delegateStateEvent");C.addEventListener("onError","Ext.ux.YoutubePlayer.Players['"+B+"']._delegateErrorEvent");A.adjustRatio(A.getWidth(),A.getHeight());A.fireEvent("ready",A,C)}};if(!window.onYouTubePlayerReady){window.onYouTubePlayerReady=_onYouTubePlayerReady}else{throw ('"onYouTubePlayerReady" is already defined. Cannot use Ext.ux.XoutubePlayer.')}Ext.namespace("Ext.ux.YoutubePlayer");Ext.ux.YoutubePlayer.Control=Ext.extend(Ext.Toolbar,{task:null,elRuntime:null,ejectButton:null,playButton:null,stopButton:null,previousButton:null,nextButton:null,muteButton:null,volumeSlider:null,sliderField:null,isAdjusting:false,_onEject:function(){var A=this;Ext.Msg.prompt("Load video","Please enter the video id or url:",function(B,C){if(B=="ok"){var D=A._parseVideoId(C);A.player.stopVideo();A.player.clearVideo();A.player.cueVideoById(D)}})},_parseVideoId:function(C){var A=C.indexOf("v=");if(A!==-1){var C=C.substring(A+2);var B=C.indexOf("&");if(B!==-1){C=C.substring(C,B)}}return C},_onError:function(C,A,B){A.stopVideo();Ext.Msg.alert("Error","The video you requested could not be played. Error code "+C)},_onSeekPosition:function(){this.player.seekTo(this.sliderField.getValue())},_onSetVolume:function(){this.muteButton.toggle(false);this.player.setVolume(this.volumeSlider.getValue())},_onMuteToggle:function(A){var C=this.muteButton.pressed;var B=false;if(A instanceof Ext.menu.Item){B=true;C=!C}if(C){A.setIconClass("ext-ux-youtubeplayer-control-muteIcon");if(B){this.muteButton.toggle(true);return }this.player.mute(true)}else{A.setIconClass("ext-ux-youtubeplayer-control-volumeIcon");if(B){this.muteButton.toggle(false);return }this.player.mute(false)}},_onPlay:function(A){var B=this.player.getPlayerState();if(B=="playing"){this.player.pauseVideo()}else{if(B=="paused"||B=="video_cued"){this.player.playVideo()}}},_onStop:function(A){this.player.pauseVideo();this.player.seekTo(0);this.stopButton.setDisabled(true);this._updateVideoInfo.defer(100,this,[true])},initComponent:function(){var A=Ext.Toolbar.Button;this.ejectButton=new A({iconCls:"eject",disabled:true});this.playButton=new A({iconCls:"play",disabled:true});this.stopButton=new A({iconCls:"stop",disabled:true});this.previousButton=new A({iconCls:"start",disabled:true});this.nextButton=new A({iconCls:"end",disabled:true});this.volumeSlider=new Ext.Slider({minValue:0,maxValue:100,width:110,disabled:true});this.sliderField=new Ext.ux.YoutubePlayer.Control.Slider({minValue:0,maxValue:0,disabled:true,listeners:{render:function(){this.el.dom.parentNode.style.width="100%"}}});this.muteButton=new Ext.Toolbar.SplitButton({iconCls:"ext-ux-youtubeplayer-control-volumeIcon",enableToggle:true,width:36,menu:new Ext.menu.Menu({enableScrolling:false,plain:true,showSeparator:false,items:[this.volumeSlider]}),handler:this._onMuteToggle,scope:this});this.elRuntime=new Ext.Toolbar.TextItem({text:"00:00"});Ext.apply(this,{cls:"ext-ux-youtubeplayer-control",items:[this.ejectButton,this.playButton,this.stopButton,this.previousButton,this.nextButton," ",this.sliderField," ",this.elRuntime,new Ext.Toolbar.Spacer(),this.muteButton]});Ext.ux.YoutubePlayer.Control.superclass.initComponent.call(this);this.on("beforerender",this._initListeners,this);this.player.on("ready",function(){this.ejectButton.setDisabled(false)},this)},_initListeners:function(){this.on("afterlayout",function(){this.getLayout().onLayout=this.getLayout().onLayout.createInterceptor(function(){this.container.sliderField.el.dom.parentNode.style.width="1px"});this.getLayout().onLayout=this.getLayout().onLayout.createSequence(function(){this.container.sliderField.el.dom.parentNode.style.width="100%"})},this,{single:true});this.muteButton.menu.on("beforeshow",function(){var B=this.player.getState();if(B!="ended"&&B!="unstarted"){this.volumeSlider.setDisabled(false);this.volumeSlider.setValue(this.player.getVolume(),false)}},this);this.playButton.on("click",this._onPlay,this);this.stopButton.on("click",this._onStop,this);this.muteButton.on("toggle",this._onMuteToggle,this);this.on("hide",this._onHide,this);this.on("destroy",this._onDestroy,this);var A=this;this.player.on("stateChange",function(D,B,C){A._processPlayerEvents.defer(1,A,[D,B,C])},this);this.sliderField.on("dragstart",function(){this.isAdjusting=true},this);this.sliderField.on("drag",this._onSeekPosition,this);this.sliderField.on("dragend",function(){this.isAdjusting=false},this);this.volumeSlider.on("drag",this._onSetVolume,this);this.player.on("error",this._onError,this);this.ejectButton.on("click",this._onEject,this)},_onDestroy:function(){if(this.task){Ext.TaskMgr.stop(this.task)}},_updateVideoInfo:function(F){if(!this.player.playerAvailable()){this._processPlayerEvents("ended",this.player,null);return }var H=this.player;var B=this.sliderField;var E=H.getVideoBytesLoaded();if(E!=-1){B.updateSliderBg(Math.floor((B.getWidth()/100)*Math.floor(((E/H.getVideoBytesTotal())*100))))}if(F!==true&&H.getPlayerState()=="paused"){return }var C=Math.max(0,H.getCurrentTime());var A=Math.max(0,H.getDuration());if(A!=0){var I=Math.floor(A-C);var D=Math.max(0,Math.floor(I/60));var G=Math.max(0,(I%60));this.elRuntime.setText((D<10?"0"+D:D)+":"+(G<10?"0"+G:G));this.sliderField.maxValue=A;if(!this.isAdjusting){this.sliderField.setValue(C,false)}}},_processPlayerEvents:function(C,A,B){switch(C){case"unstarted":this._un=true;break;case"ended":if(this.task){Ext.TaskMgr.stop(this.task);this.task=null}this.playButton.setIconClass("play");this.sliderField.setValue(0);this.sliderField.setDisabled(true);this.sliderField.updateSliderBg(0);this.elRuntime.setText("00:00");if(this.volumeField){this.volumeField.setDisabled(true)}this.playButton.setDisabled(true);this.stopButton.setDisabled(true);this.muteButton.setDisabled(true);if(A.videoId&&!this._un){this._un=true;A.cueVideoById(A.videoId,0)}break;case"playing":if(!this.task){var D=this;this.task={run:function(){D._updateVideoInfo()},interval:500};Ext.TaskMgr.start(this.task)}this._un=false;this.sliderField.setDisabled(false);if(this.volumeField){this.volumeField.setDisabled(false)}this.playButton.setIconClass("pause");this.playButton.setDisabled(false);this.stopButton.setDisabled(false);this.muteButton.setDisabled(false);break;case"paused":this.playButton.setIconClass("play");break;case"buffering":break;case"video_cued":this.playButton.setDisabled(false);break;case"unknown":break}}});Ext.ux.YoutubePlayer.Control.Slider=Ext.extend(Ext.Slider,{cls:"ext-ux-youtubeplayer-control-slider",onRender:function(){Ext.ux.YoutubePlayer.Control.Slider.superclass.onRender.apply(this,arguments);this.progress=document.createElement("div");this.progress.className="hbar";this.el.dom.appendChild(this.progress)},updateSliderBg:function(A){this.progress.style.backgroundPosition="-"+(1280-A)+"px 0"}});