<div>
    <h3>{translate key="plugins.generic.docMapReviews.displayReviewsPreferences"}</h3>

    <p>{translate key="plugins.generic.docMapReviews.prePubDescriptionPartOne"}</p>
    <p>{translate key="plugins.generic.docMapReviews.prePubDescriptionPartTwo"}</p>
    <p>{translate key="plugins.generic.docMapReviews.prePubDescriptionPartThree"}</p>
    <div id="displayReviewsPreferences">
        {capture assign=reviewOfferPrefsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.docMapReviews.controllers.grid.DocMapReviewsGridHandler" op="fetchGrid" submissionId=$submissionId escape=false}{/capture}
        {load_url_in_div id="docMapsReviewPrefsGridContainer"|uniqid url=$reviewOfferPrefsGridUrl}
    </div>
</div>
<br>