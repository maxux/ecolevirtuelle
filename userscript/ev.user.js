// ==UserScript==
// @name          Ecole Virtuelle AutoLogin
// @namespace     https://www2.maxux.net
// @description	  Add an autologin box for ecolevirtuelle
// @include       https://ecolevirtuelle.provincedeliege.be/*
// @match         https://ecolevirtuelle.provincedeliege.be/*
// ==/UserScript==

// a function that loads jQuery and calls a callback function when jQuery has finished loading
function addJQuery(callback) {
        var script = document.createElement("script");
        
        script.setAttribute("src", "//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js");
        script.addEventListener('load', function() {
                var script = document.createElement("script");
                script.textContent = "window.jQ=jQuery.noConflict(true);(" + callback.toString() + ")();";
                document.body.appendChild(script);
        }, false);
        
        document.body.appendChild(script);
}

// the guts of this userscript
function main() {
        // Note, jQ replaces $ to avoid conflicts.
        
        /* if(!this.GM_getValue || (this.GM_getValue.toString && this.GM_getValue.toString().indexOf("not supported")>-1)) {
                this.GM_getValue=function (key,def) {
                        return localStorage[key] || def;
                };
                
                this.GM_setValue=function (key,value) {
                        return localStorage[key]=value;
                };
                
                this.GM_deleteValue=function (key) {
                        return delete localStorage[key];
                };
        } */

        function createCookie(name, value, days) {
                var date = new Date();
                date.setTime(date.getTime()+(days*24*60*60*1000));
                
                var expires = "; expires="+date.toGMTString();
                document.cookie = name + "=" + value + expires + "; path=/";
        }
        
        function readCookie(name) {
                var nameEQ = name + "=";
                var ca = document.cookie.split(';');
                
                for(var i = 0; i < ca.length; i++) {
                        var c = ca[i];
                        while(c.charAt(0)==' ')
                                c = c.substring(1, c.length);
                                
                        if(c.indexOf(nameEQ) == 0)
                                return c.substring(nameEQ.length, c.length);
                }
                
                return null;
        }

        function eraseCookie(name) {
                createCookie(name, "", -1);
        }
        
        jQ('.connection').append('<input type="checkbox" name="save" id="save" value="yes"><label for="save">Enregistrer mon login</label>');
        
        if((uname = readCookie('__username'))) {
                jQ('#username').val(uname);
                jQ('#password').val(readCookie('__password'));
                jQ('#save').attr('checked', 'checked');
                
                // load jQuery and execute the main function
                if(window.location == 'https://ecolevirtuelle.provincedeliege.be/') {
                        eraseCookie('ecovsess');
                        jQ('form input[type="image"]').click();
                }
        }

        
        jQ('form input[type="image"]').click(function() {
                if(jQ('#save').attr('checked') == 'checked') {
                        createCookie('__username', jQ('#username').val(), 365);
                        createCookie('__password', jQ('#password').val(), 365);
                } else {
                        eraseCookie('__username');
                        eraseCookie('__password');
                }
        });
}

addJQuery(main);
