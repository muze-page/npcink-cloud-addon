( function () {
	'use strict';

	const config = window.npcinkCloudEntitlement || {};
	const container = document.querySelector( '[data-npcink-entitlement-state]' );

	if ( ! container || ! config.ajaxUrl || ! config.action || ! config.nonce ) {
		return;
	}

	const summary = container.querySelector( '[data-npcink-entitlement-summary]' );
	const retry = container.querySelector( '[data-npcink-entitlement-retry]' );
	const spinner = container.querySelector( '.npcink-cloud-entitlement__spinner' );
	const metricRows = document.querySelectorAll( '[data-npcink-entitlement-metric]' );
	const initialState = container.dataset.npcinkEntitlementState || '';
	const initialLabel = summary ? summary.textContent : '';
	let requestInFlight = false;

	if ( ! summary || ! retry ) {
		return;
	}

	const setLoading = ( loading ) => {
		requestInFlight = loading;
		container.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		retry.disabled = loading;
		if ( spinner ) {
			spinner.classList.toggle( 'is-active', loading );
		}
	};

	const updateMetrics = ( metrics ) => {
		metricRows.forEach( ( row ) => {
			const key = row.dataset.npcinkEntitlementMetric || '';
			const metric = metrics && metrics[ key ] ? metrics[ key ] : {};
			const label = row.querySelector( '[data-npcink-entitlement-metric-label]' );
			const progress = row.querySelector( '[data-npcink-entitlement-progress]' );

			row.hidden = ! metric.available;
			if ( label ) {
				label.textContent = metric.available && metric.label ? metric.label : '';
			}
			if ( progress ) {
				const hasPercent = null !== metric.percent && '' !== metric.percent && Number.isFinite( Number( metric.percent ) );
				progress.hidden = ! hasPercent;
				if ( hasPercent ) {
					progress.value = Math.max( 0, Math.min( 100, Number( metric.percent ) ) );
				}
			}
		} );
	};

	const refresh = async ( mode ) => {
		if ( requestInFlight ) {
			return;
		}

		setLoading( true );
		if ( 'not_refreshed' === initialState ) {
			retry.hidden = true;
		}

		const body = new URLSearchParams( {
			action: config.action,
			nonce: config.nonce,
			mode,
		} );

		try {
			const response = await window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			} );
			const payload = await response.json();

			if ( ! response.ok || ! payload.success || ! payload.data || ! payload.data.label ) {
				throw new Error( 'entitlement_refresh_failed' );
			}

			summary.textContent = payload.data.label;
			container.dataset.npcinkEntitlementState = payload.data.state || 'fresh';
			updateMetrics( payload.data.metrics || {} );
			retry.hidden = true;
		} catch ( error ) {
			const hasRetainedSummary = 'stale' === initialState && '' !== initialLabel;
			summary.textContent = hasRetainedSummary
				? initialLabel + ' · ' + ( config.updateFailedLabel || 'Update failed' )
				: ( config.failedLabel || 'Plan and entitlement are temporarily unavailable.' );
			container.dataset.npcinkEntitlementState = 'unavailable';
			retry.hidden = false;
		} finally {
			setLoading( false );
		}
	};

	retry.addEventListener( 'click', () => refresh( 'retry' ) );

	if ( 'not_refreshed' === initialState || 'stale' === initialState ) {
		refresh( 'auto' );
	}
}() );
