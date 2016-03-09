//js基础类
(function(that){

	//server脚本
	that.action = 'http://yourdomain/weixinjsapi/service/src/weixinApi.php';		

	//初始化
	that.init = function(appid, apilist, debug){
		var mdebug = debug || false;
		//获取url #号前面的部分
		var str = location.href;
		if(str.search(/#/)<0){
			murl = encodeURIComponent(str);
		}else{
			murl = encodeURIComponent(str.match(/.*#/)[0].substr(0,str.match(/.*#/)[0].length-1));
		}

		var jsapi_ticket = that.getCookie('jsapi_ticket');
		if(jsapi_ticket == '' || jsapi_ticket == null || jsapi_ticket == undefined || jsapi_ticket == 'null'){
			url = that.action + '?url=' + murl + '&appid=' + appid;
		}else{
			url = that.action + '?url=' + murl + '&appid=' + appid + '&jsapi_ticket=' + jsapi_ticket;
		}
		that.Majax('get', url, mdebug, appid, apilist); 
	}


	//配置微信
	weixinConfig = function(debug, appid, timestamp, noncestr, signature, jsApiList){
		//默认配置
		wx.config({
			debug: debug, // 开启调试模式
			appId: appid, // 必填，公众号的唯一标识
			timestamp: timestamp, // 必填，生成签名的时间戳
			nonceStr: noncestr, // 必填，生成签名的随机串
			signature: signature,// 必填，签名，见附录1
			jsApiList: jsApiList // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
		});	
	}


	//获取cookie
	that.getCookie = function(name){ 
		var strCookie=document.cookie; 
		var arrCookie=strCookie.split("; "); 
		for(var i=0;i<arrCookie.length;i++){ 
			var arr=arrCookie[i].split("="); 
			if(arr[0]==name)
				return decodeURIComponent(arr[1]); 
		} 
		return ""; 
	}

	//设置cookie
	that.addCookie = function(name,value,expiresHours){ 
		var cookieString=name+"="+escape(value); 
		//判断是否设置过期时间 
		if(expiresHours>0){ 
			var date=new Date(); 
			date.setTime(date.getTime+expiresHours*3600*1000); 
			cookieString=cookieString+"; expires="+date.toGMTString(); 
		} 
		document.cookie=cookieString; 
	}

	//ajax提交
	that.Majax = function (type, url, mdebug, appid, apilist){
		$.ajax({  
			type : type,  
			async:false,  
			url : url,  
			dataType : "jsonp",//数据类型为jsonp  
			jsonp: "jsonpCallback",//服务端用于接收callback调用的function名的参数  
			jsonpCallback:"success_jsonpCallback",
			success : function(data){  
				if(data.code == 200) {
					console.log(data.jsapi_ticket);
					that.addCookie('jsapi_ticket', data.jsapi_ticket, 2);
					weixinConfig(mdebug, appid, data.timestamp, data.noncestr, data.signature, apilist);
				}
			},  
			error:function(){  
				console.log('ajax error');
			}  		
		})
	}

})(window.weixinApi = {})
