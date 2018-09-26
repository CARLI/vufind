/*global VuFind */
/*exported setUpILLRequestForm */
function setUpILLRequestForm(recordId) {
  $("#ILLRequestForm #pickupLibrary").change(function illPickupChange() {
    // CARLI EDIT: (re-)hide the submit button until there is now a valid pickup location!
    $('input[type="submit"]').addClass('hidden');

    $("#ILLRequestForm #pickupLibraryLocation option").remove();
    $("#ILLRequestForm #pickupLibraryLocationLabel i").addClass("fa fa-spinner icon-spin");
    var url = VuFind.path + '/AJAX/JSON?' + $.param({
      id: recordId,
      method: 'getLibraryPickupLocations',
      pickupLib: $("#ILLRequestForm #pickupLibrary").val()
    });
    $.ajax({
      dataType: 'json',
      cache: false,
      url: url
    })
    .done(function illPickupLocationsDone(response) {
      $.each(response.data.locations, function illPickupLocationEach() {
        var option = $("<option></option>").attr("value", this.id).text(this.name);
        if (this.isDefault) {
          option.attr("selected", "selected");
        }
        $("#ILLRequestForm #pickupLibraryLocation").append(option);
      });
      $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");

      // CARLI EDIT: un-hide the submit button because there is now a valid pickup location!
      $('input[type="submit"]').removeClass('hidden');

    })
    .fail(function illPickupLocationsFail(/*response*/) {
      $("#ILLRequestForm #pickupLibraryLocationLabel i").removeClass("fa fa-spinner icon-spin");
    });
  });
  $("#ILLRequestForm #pickupLibrary").change();
}
