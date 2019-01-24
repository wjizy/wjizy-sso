var $_sso = {
	domain:'http://www.sso.com',
	appid:'',
	head:null,
	url:'/jsonp',
	url2:'/iframe',
	url3:'/quit',
	dtime:5000,
	init: function(appid, domain){
		this.domain = domain || this.domain
		this.appid = appid;
		this.head = document.head || document.getElementsByTagName('head')[0];
		this.create_iframe(appid)
	},
	create_iframe: function(appid){
		 var iframe = document.createElement('iframe');
		 iframe.setAttribute("src",encodeURI(this.domain + this.url2 + '/' + appid));
		 iframe.setAttribute("id",'iframe_'+appid);
		 iframe.style="display:none;"
		 this.head.appendChild(iframe);
		 this.deliframe();
	},
	create_script: function(url){
		 var script = document.createElement('script');
		 script.setAttribute("src",encodeURI(url));
		 script.setAttribute("id",'script_'+this.appid);
		 this.head.appendChild(script);
		 this.delscript();
	},
	login: function(name, password, appid){
		name = window.btoa(encodeURIComponent(name))
		password = window.btoa(encodeURIComponent(password))
	    var url = this.domain + this.url + "?id=" + appid + "&a=" + name +'&p=' + password +'&callback=$_sso.callback'
		this.create_script(url);
		return this;
	},
	quit: function(){
		var url = this.domain + this.url3 +"?callback=$_sso.callback"
		this.create_script(url);
		return this;
	},
	handle: function(res){
		console.log(res);
	},
	callback: function(res){
	    this.create_iframe(this.appid)
        this.handle(JSON.parse(res));
	},
	delscript: function () {
		var self = this;
		setTimeout(function () {
			var script = document.getElementById('script_'+self.appid);
			if(script){
				self.head.removeChild(script);
			}
		},self.dtime);
	},
	deliframe: function () {
		var self = this;
		setTimeout(function () {
			var iframe = document.getElementById('iframe_'+self.appid);
			if(iframe){
				self.head.removeChild(iframe);
			}
		},self.dtime);
	}
}
