var linesShown = 0;
var charWidth = 0;
var charHeight = 13.5; // see css
var headerHeight = 36; // see css
var pastePaddingTop = 3; // see css
var linesMarginLeft = 0;
var elLines = null;
var elPaste = null;

document.addEventListener('DOMContentLoaded', function() {
	document.body.className = 'js';
	elLines = document.getElementById('lines');
	elPaste = document.getElementById('paste');

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
});

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
	elPaste.style.width = (Math.max(minWidth, charWidth * longestLineChars) + 36) + 'px';
}

function scroll() {
	var scrollY = ('scrollY' in window ? window.scrollY : document.documentElement.scrollTop);
	elLines.style.marginTop = (-scrollY) + 'px';
}
