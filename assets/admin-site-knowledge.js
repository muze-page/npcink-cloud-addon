( function () {
	'use strict';

	const config = window.npcinkCloudSiteKnowledge || {};
	const container = document.querySelector( '[data-npcink-site-knowledge-usage]' );

	if ( ! container || ! config.ajaxUrl || ! config.action || ! config.nonce ) {
		return;
	}

	const label = container.querySelector( '[data-npcink-site-knowledge-usage-label]' );
	const progress = container.querySelector( '[data-npcink-site-knowledge-progress]' );
	const retry = container.querySelector( '[data-npcink-site-knowledge-retry]' );
	const spinner = container.querySelector( '.npcink-cloud-site-knowledge-usage__spinner' );
	const detailRows = document.querySelectorAll( '[data-npcink-site-knowledge-detail]' );
	const initialState = container.dataset.npcinkSiteKnowledgeState || '';
	const initialLabel = label ? label.textContent : '';
	let requestInFlight = false;

	if ( ! label || ! retry ) {
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

	const updateDetails = ( details ) => {
		detailRows.forEach( ( row ) => {
			const key = row.dataset.npcinkSiteKnowledgeDetail || '';
			const detail = details && details[ key ] ? details[ key ] : {};
			const detailLabel = row.querySelector( '[data-npcink-site-knowledge-detail-label]' );
			const detailValue = row.querySelector( '[data-npcink-site-knowledge-detail-value]' );

			row.hidden = ! detail.available;
			if ( detailLabel && detail.label ) {
				detailLabel.textContent = detail.label;
			}
			if ( detailValue ) {
				detailValue.textContent = detail.available && detail.value ? detail.value : '';
			}
		} );
	};

	const updateUsage = ( usage ) => {
		label.textContent = usage.label || config.failedLabel || '';
		container.dataset.npcinkSiteKnowledgeState = usage.state || 'fresh';
		container.title = usage.tooltip || '';

		if ( progress ) {
			const hasPercent = null !== usage.percent && '' !== usage.percent && Number.isFinite( Number( usage.percent ) );
			progress.hidden = ! usage.available || ! hasPercent;
			progress.classList.remove(
				'npcink-cloud-site-knowledge-progress--ok',
				'npcink-cloud-site-knowledge-progress--warning',
				'npcink-cloud-site-knowledge-progress--error'
			);
			progress.classList.add( 'npcink-cloud-site-knowledge-progress--' + ( usage.severity || 'ok' ) );
			if ( hasPercent ) {
				progress.value = Math.max( 0, Math.min( 100, Number( usage.percent ) ) );
			}
		}

		updateDetails( usage.details || {} );
	};

	const refresh = async () => {
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

			if ( ! response.ok || ! payload.success || ! payload.data || ! payload.data.available ) {
				throw new Error( 'site_knowledge_usage_refresh_failed' );
			}

			updateUsage( payload.data );
			retry.hidden = true;
		} catch ( error ) {
			const hasRetainedUsage = 'stale' === initialState && '' !== initialLabel;
			label.textContent = hasRetainedUsage
				? initialLabel + ' · ' + ( config.updateFailedLabel || 'Update failed' )
				: ( config.failedLabel || 'Site Knowledge usage is temporarily unavailable.' );
			container.dataset.npcinkSiteKnowledgeState = 'unavailable';
			retry.hidden = false;
		} finally {
			setLoading( false );
		}
	};

	retry.addEventListener( 'click', refresh );

	if ( 'not_refreshed' === initialState || 'stale' === initialState ) {
		refresh();
	}
}() );
