var elPaste = null;
var elSend = null;
var elTitle = null;

document.addEventListener('DOMContentLoaded', function() {
	document.body.className += ' js';

	var elHeader = document.getElementById('header');
	var elLogo = document.getElementById('logo');
	var elLogoText = document.getElementById('logotext');
	function logoEnter() { elHeader.className = 'logo-hover'; }
	function logoLeave() { elHeader.className = ''; }
	elLogo.addEventListener('mouseenter', logoEnter);
	elLogo.addEventListener('mouseleave', logoLeave);
	elLogoText.addEventListener('mouseenter', logoEnter);
	elLogoText.addEventListener('mouseleave', logoLeave);
	elLogoText.addEventListener('focus', logoEnter);
	elLogoText.addEventListener('blur', logoLeave);
	elLogo.addEventListener('click', function() {
		elLogoText.focus();
		elLogoText.click();
	});
	elLogoText.addEventListener('click', clearPaste);
	var elNavNewPaste = document.getElementById('nav-new-paste');
	if (elNavNewPaste) {
		elNavNewPaste.addEventListener('click', clearPaste);
	}

	elPaste = document.getElementById('paste');
	elSend = document.getElementById('send');
	elTitle = document.getElementById('title');

	if (!elPaste) {
		return;
	}

	elPaste.focus();

	if (elSend) {
		var s = debounce(savePaste, 1500);
		elPaste.addEventListener('input', s);
		elTitle.addEventListener('input', s);

		elSend.addEventListener('click', function(e) {
			lset('login', {
				username: document.getElementById('username').value,
				password: document.getElementById('password').value,
			});
			savePaste();

			if (elTitle.value.length < 3) {
				alert('Paste title is required');
				e.preventDefault();
				return;
			}
			if (elPaste.value.length < 3) {
				alert('Paste content is required');
				e.preventDefault();
				return;
			}

			setTimeout(function() {
				elSend.disabled = true;
				elSend.value = 'SAVING…';
			}, 0);
			setTimeout(function() {
				elSend.disabled = false;
				elSend.value = 'SAVE';
			}, 6000);
			// for Back button
			window.onbeforeunload = function() {
				elSend.disabled = false;
				elSend.value = 'SAVE';
			};
		});

		var saved = lget('login');
		if (saved) {
			document.getElementById('username').value = saved.username;
			document.getElementById('password').value = saved.password;
		}
		saved = lget('savedPaste');
		if (saved && window.location.pathname === '/') { // new paste
			elTitle.value = saved.title;
			elPaste.value = saved.paste;
			elPaste.selectionStart = elPaste.selectionEnd = 0;
		}
	}

	var elShowPasteInfo = document.getElementById('show-paste-info');
	if (elShowPasteInfo) {
		elShowPasteInfo.addEventListener('click', function(e) {
			e.preventDefault();
			alert(
				'Paste info:\n\n'
				+ JSON.parse(elShowPasteInfo.getAttribute('data-info'))
			);
		});
	}

	var elTogglePw = document.getElementById('toggle-pw');
	if (elTogglePw) {
		elTogglePw.addEventListener('click', function(e) {
			e.preventDefault();
			var elPass = document.getElementById('password');
			if (elPass.type === 'password') {
				elPass.type = 'text';
				elTogglePw.innerText = 'hide pw';
			} else {
				elPass.type = 'password';
				elTogglePw.innerText = 'show pw';
			}
		});
	}
});

function savePaste() {
	console.log('saving paste to localStorage');
	lset('savedPaste', {
		title: elTitle.value,
		paste: elPaste.value,
	});
}

function clearPaste() {
	if (elSend) {
		elTitle.value = '';
		elPaste.value = '';
	}
	lremove('savedPaste');
}

function debounce(fn, ms) {
	var last = 0;
	var t = null;
	return function() {
		var now = Date.now();
		function run() {
			fn();
			last = now;
		}
		if (t) clearTimeout(t);
		if (now - last > ms) {
			run();
		} else {
			t = setTimeout(run, ms);
		}
	};
}

function lset(name, value) {
	try {
		localStorage.setItem(name, JSON.stringify(value));
	} catch {}
}

function lget(name) {
	try {
		return JSON.parse(localStorage.getItem(name));
	} catch {
		return null;
	}
}

function lremove(name) {
	try {
		localStorage.removeItem(name);
	} catch {}
}
