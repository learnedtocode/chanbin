var linesShown = 0;
var charWidth = 0;
var charHeight = 13.5; // see css
var headerHeight = 36; // see css
var lines = null;
var paste = null;
var linesMarginLeft = 0;

document.addEventListener('DOMContentLoaded', function() {
	document.body.className = 'js';
	lines = document.getElementById('lines');
	paste = document.getElementById('paste');

	calcCharWidth();
	resize();
	scroll();
	followCursor();

	window.addEventListener('scroll', scroll);
	var r = debounce(resize, 60);
	window.addEventListener('resize', r);
	paste.addEventListener('input', r);
	var f = debounce(followCursor, 30);
	paste.addEventListener('input', f);
	paste.addEventListener('select', f);
	paste.addEventListener('keydown', f);
	paste.addEventListener('keyup', f);
	paste.addEventListener('click', f);
	paste.addEventListener('focus', f);
});

function followCursor() {
	var ss = paste.selectionStart;
	if (ss !== paste.selectionEnd) return;
	var val = paste.value;
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
	var paddingTop = 3;
	var viewport = {
		minRow: Math.ceil((de.scrollTop - paddingTop) / charHeight),
		maxRow: Math.floor((de.scrollTop + de.clientHeight - headerHeight) / charHeight),
		minCol: Math.ceil(de.scrollLeft / charWidth),
		maxCol: Math.floor((de.scrollLeft + de.clientWidth - linesMarginLeft) / charWidth)
	};
	if (viewport.minRow > row) {
		de.scrollTop = Math.max(row, 0) * charHeight + paddingTop;
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
	var pasteLines = paste.value.split('\n');
	var longestLineChars = 0;
	var i;

	for (i = 0; i < pasteLines.length; i++) {
		longestLineChars = Math.max(longestLineChars, pasteLines[i].length);
	}

	if (linesShown != pasteLines.length) {
		var linesText = '';
		var numChars = String(pasteLines.length).length;
		for (i = 1; i <= pasteLines.length; i++) {
			linesText += i;
			if (i < pasteLines.length) linesText += '\n';
		}
		linesText += '\n\n\n\n\n\n';
		lines.innerText = linesText;
		linesShown = pasteLines.length;
		linesMarginLeft = charWidth * numChars + 9;
		lines.style.width = paste.style.marginLeft = linesMarginLeft + 'px';
		paste.style.height = (pasteLines.length * charHeight + 36) + 'px';
		var minWidth = document.documentElement.clientWidth - linesMarginLeft - 1;
		paste.style.width = (Math.max(minWidth, charWidth * longestLineChars) + 36) + 'px';
	}
}

function scroll() {
	var scrollY = ('scrollY' in window ? window.scrollY : document.documentElement.scrollTop);
	lines.style.marginTop = (-scrollY) + 'px';
}
