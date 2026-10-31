/* Categories: preview and apply the rules that sort the old posts, in small batches (inc/category-manager.php). */
( function () {
	'use strict';
	var C = window.mcuprCat, prev = document.getElementById( 'mcupr-cat-preview' ), apply = document.getElementById( 'mcupr-cat-apply' );
	if ( ! C || ! prev || ! apply ) { return; }
	var ok = document.getElementById( 'mcupr-cat-ok' ), repl = document.getElementById( 'mcupr-cat-replace' ), stop = document.getElementById( 'mcupr-cat-stop' ),
		bar = document.getElementById( 'mcupr-cat-bar' ), msg = document.getElementById( 'mcupr-cat-msg' ), list = document.getElementById( 'mcupr-cat-sample' ), halt = false;
	function fmt( s ) { var a = Array.prototype.slice.call( arguments, 1 ); return s.replace( /%(\d)\$[ds]/g, function ( m, n ) { return a[ n - 1 ]; } ); }
	function busy( on ) {
		stop.hidden = ! on; bar.hidden = ! on; ok.disabled = on; repl.disabled = on; prev.disabled = on; apply.disabled = on || ! ok.checked;
	}
	ok.addEventListener( 'change', function () { apply.disabled = ! ok.checked; } );
	function run( doApply ) {
		var st = { seen: 0, changed: 0, total: 0, sample: [] };
		halt = false; busy( true ); msg.textContent = ''; list.innerHTML = '';
		function finish( text ) {
			busy( false );
			msg.textContent = text;
			if ( st.sample.length ) {
				var li = document.createElement( 'li' ); li.textContent = C.t.sample; li.style.listStyle = 'none'; li.style.marginLeft = '-1.2rem'; list.appendChild( li );
				st.sample.forEach( function ( t ) { var x = document.createElement( 'li' ); x.textContent = t; list.appendChild( x ); } );
			}
		}
		function step( after ) {
			var fd = new FormData();
			fd.append( 'action', 'mcupr_cat_run' ); fd.append( 'nonce', C.nonce ); fd.append( 'after', after );
			if ( doApply ) { fd.append( 'apply', '1' ); fd.append( 'confirm', '1' ); }
			if ( repl.checked ) { fd.append( 'replace', '1' ); }
			fetch( C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
				if ( ! j || ! j.success ) { throw new Error( ( j && j.data && j.data.message ) || 'x' ); }
				var d = j.data;
				st.seen += d.n; st.changed += d.changed; st.total = d.total;
				d.sample.forEach( function ( t ) { if ( st.sample.length < 8 ) { st.sample.push( t ); } } );
				bar.max = Math.max( 1, st.total ); bar.value = Math.min( st.seen, st.total );
				msg.textContent = fmt( doApply ? C.t.runApply : C.t.run, st.seen, st.total, st.changed );
				if ( ! d.more || ! d.n || halt ) {
					finish( fmt( doApply ? C.t.applyDone : C.t.previewDone, st.changed, st.total ) );
				} else { step( d.last ); }
			} ).catch( function ( e ) { finish( C.t.fail + ( e && e.message && 'x' !== e.message ? ' ' + e.message : '' ) ); } );
		}
		step( 0 );
	}
	prev.addEventListener( 'click', function () { run( false ); } );
	apply.addEventListener( 'click', function () { run( true ); } );
	stop.addEventListener( 'click', function () { halt = true; } );
}() );
