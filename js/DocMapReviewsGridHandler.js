(function($) {

	/** @type {Object} */
	$.pkp.plugins.generic.docMapReviews =
		$.pkp.plugins.generic.docMapReviews ||
		{ js: { } };

	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.CategoryGridHandler
	 *
	 * @param {jQueryObject} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.plugins.generic.docMapReviews.DocMapReviewsGridHandler =
			function($grid, options) {
		this.parent($grid, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.plugins.generic.docMapReviews.DocMapReviewsGridHandler,
			$.pkp.controllers.grid.GridHandler);

	//
	// Public methods.
	//

	/**
	 * Refresh the whole grid.
	 *
	 * @protected
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @param {number|Object=} opt_elementId The submissionId
	 *  @param {Boolean=} opt_fetchedAlready Flag that subclasses can send
	 *  telling that a fetch operation was already handled there.
	 */
	$.pkp.plugins.generic.docMapReviews.DocMapReviewsGridHandler.prototype.refreshGridHandler =
			function(sourceElement, event, opt_elementId, opt_fetchedAlready) {
		var params;

		params = this.getFetchExtraParams();

		// Check if subclasses already handled the fetch of new elements.
		if (!opt_fetchedAlready) {
			params.submissionId = opt_elementId;
			$.get(this.fetchGridUrl_, params,
				this.callbackWrapper(this.replaceGridResponseHandler_), 'json');
		}

		// Let the calling context (page?) know that the grids are being redrawn.
		this.trigger('gridRefreshRequested');
		this.publishChangeEvents();
	};

}(jQuery));