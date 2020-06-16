var linesShown = 0;
var charWidth = 0;
var charHeight = 13.5; // see css
var headerHeight = 36; // see css
var pastePaddingTop = 3; // see css
var linesMarginLeft = 0;
var elLines = null;
var elPaste = null;
var elSend = null;
var elTitle = null;

document.addEventListener('DOMContentLoaded', function() {
	document.body.className = 'js';

	var elHeader = document.getElementById('header');
	var elLogo = document.getElementById('logo');
	var elLogoText = document.getElementById('logotext');
	function logoEnter() { elHeader.className = 'logo-hover'; }
	function logoLeave() { elHeader.className = ''; }
	elLogo.addEventListener('mouseenter', logoEnter);
	elLogo.addEventListener('mouseleave', logoLeave);
	elLogoText.addEventListener('mouseenter', logoEnter);
	elLogoText.addEventListener('mouseleave', logoLeave);
	elLogo.addEventListener('click', function() {
		elLogoText.focus();
		elLogoText.click();
	});
	elLogoText.addEventListener('click', function(e) {
		if (window.location.pathname === '/') { // new paste
			e.preventDefault();
			elTitle.value = '';
			elPaste.select();
			elPaste.focus();
		}
	});

	elLines = document.getElementById('lines');
	elPaste = document.getElementById('paste');
	elSend = document.getElementById('send');
	elTitle = document.getElementById('title');

	if (!elPaste) {
		return;
	}

	calcCharWidth();
	resize();
	scroll();
	followCursor();

	window.addEventListener('scroll', scroll);

	var r = debounce(resize, 60);
	window.addEventListener('resize', r);
	elPaste.addEventListener('input', r);

	var f = debounce(followCursor, 60);
	elPaste.addEventListener('input', f);
	elPaste.addEventListener('select', f);
	elPaste.addEventListener('keydown', f);
	elPaste.addEventListener('keyup', f);
	elPaste.addEventListener('click', f);
	elPaste.addEventListener('focus', f);

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
			if (window.location.hash !== '#go') {
				alert('This is just a preview, not ready for use yet');
				e.preventDefault();
			}
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
			resize();
			scroll();
			followCursor();
		}
	}
});

function savePaste() {
	console.log('saving paste to localStorage');
	lset('savedPaste', {
		title: elTitle.value,
		paste: elPaste.value,
	});
}

function followCursor() {
	var ss = elPaste.selectionStart;
	if (ss !== elPaste.selectionEnd) return;
	var val = elPaste.value;
	var pos = 0;
	var row = 0;
	var col = 0;
	while (true) {
		var next = val.indexOf('\n', pos);
		if (next >= ss || next === -1) {
			col = ss - pos;
			break;
		}
		pos = next + 1;
		row++;
	}
	var de = document.documentElement;
	var viewport = {
		minRow: Math.ceil((de.scrollTop - pastePaddingTop) / charHeight),
		maxRow: Math.floor((de.scrollTop + de.clientHeight - headerHeight) / charHeight),
		minCol: Math.ceil(de.scrollLeft / charWidth),
		maxCol: Math.floor((de.scrollLeft + de.clientWidth - linesMarginLeft) / charWidth)
	};
	if (viewport.minRow > row) {
		de.scrollTop = Math.max(row, 0) * charHeight + pastePaddingTop;
	} else if (viewport.maxRow < row) {
		de.scrollTop = (row + 3) * charHeight - de.clientHeight + headerHeight;
	}
	if (viewport.minCol > col) {
		de.scrollLeft = Math.max(col - 4, 0) * charWidth;
	} else if (viewport.maxCol < col + 3) {
		de.scrollLeft = (col + 6) * charWidth - de.clientWidth;
	}
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

function calcCharWidth() {
	var calc = document.createElement('span');
	calc.id = 'calc';
	calc.innerText = 'abcdefghi';
	document.body.appendChild(calc);
	charWidth = calc.clientWidth / 9;
	document.body.removeChild(calc);
}

function resize() {
	var pasteLines = elPaste.value.split('\n');
	var longestLineChars = 0;
	var i;

	for (i = 0; i < pasteLines.length; i++) {
		longestLineChars = Math.max(longestLineChars, pasteLines[i].length);
	}

	if (linesShown != pasteLines.length) {
		var linesText = '';
		var numChars = Math.max(3, String(pasteLines.length).length);
		for (i = 1; i <= pasteLines.length; i++) {
			linesText += i;
			if (i < pasteLines.length) linesText += '\n';
		}
		linesText += '\n\n\n\n\n\n';
		elLines.innerText = linesText;
		linesShown = pasteLines.length;
		linesMarginLeft = charWidth * numChars + 9;
	}

	elLines.style.width = elPaste.style.marginLeft = linesMarginLeft + 'px';
	elPaste.style.height = (pasteLines.length * charHeight + 36) + 'px';
	var minWidth = document.documentElement.clientWidth - linesMarginLeft - 1;
	elPaste.style.width = (Math.max(minWidth, charWidth * longestLineChars + 36)) + 'px';
}

function scroll() {
	var scrollY = ('scrollY' in window ? window.scrollY : document.documentElement.scrollTop);
	elLines.style.marginTop = (-scrollY) + 'px';
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
