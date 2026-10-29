/* Pictures (WebP): convert the whole Media Library in small batches (inc/images-bulk.php). */
( function () {
	'use strict';
	var C = window.mcuprImgb, go = document.getElementById( 'mcupr-imgb-go' );
	if ( ! C || ! go ) { return; }
	var ok = document.getElementById( 'mcupr-imgb-ok' ), again = document.getElementById( 'mcupr-imgb-again' ), stop = document.getElementById( 'mcupr-imgb-stop' ),
		bar = document.getElementById( 'mcupr-imgb-bar' ), msg = document.getElementById( 'mcupr-imgb-msg' ), halt = false;
	function fmt( s ) { var a = Array.prototype.slice.call( arguments, 1 ); return s.replace( /%(\d)\$[ds]/g, function ( m, n ) { return a[ n - 1 ]; } ); }
	function size( b ) { return b >= 1048576 ? ( b / 1048576 ).toFixed( 1 ) + ' MB' : Math.round( b / 1024 ) + ' KB'; }
	ok.addEventListener( 'change', function () { go.disabled = ! ok.checked; } );
	go.addEventListener( 'click', function () {
		var st = { conv: 0, kept: 0, bad: 0, saved: 0, total: 0, seen: 0 };
		halt = false; go.disabled = true; stop.hidden = false; bar.hidden = false; ok.disabled = true; again.disabled = true; msg.textContent = '';
		function finish( text ) {
			stop.hidden = true; bar.hidden = true; ok.disabled = false; again.disabled = false; go.disabled = ! ok.checked;
			msg.textContent = fmt( text, st.conv, st.kept, st.bad, size( st.saved ) );
		}
		function step( after ) {
			var fd = new FormData();
			fd.append( 'action', 'mcupr_imgb' ); fd.append( 'nonce', C.nonce ); fd.append( 'confirm', '1' ); fd.append( 'after', after );
			if ( again.checked ) { fd.append( 'again', '1' ); }
			fetch( C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
				if ( ! j || ! j.success ) { throw new Error( ( j && j.data && j.data.message ) || 'x' ); }
				var d = j.data;
				d.items.forEach( function ( it ) {
					st.seen++;
					if ( 'converted' === it.st ) { st.conv++; st.saved += Math.max( 0, it.before - it.after ); } else if ( 'kept' === it.st ) { st.kept++; } else { st.bad++; }
				} );
				if ( ! st.total ) { st.total = st.seen + d.left; }
				bar.max = Math.max( 1, st.total ); bar.value = st.seen;
				msg.textContent = fmt( C.t.run, st.seen, st.total );
				if ( d.end || ! d.items.length ) { finish( st.seen ? C.t.done : C.t.none ); } else if ( halt ) { finish( C.t.stopped ); } else { step( d.last ); }
			} ).catch( function ( e ) { finish( C.t.fail ); if ( e && e.message && 'x' !== e.message ) { msg.textContent += ' ' + e.message; } } );
		}
		step( 0 );
	} );
	stop.addEventListener( 'click', function () { halt = true; } );
}() );
