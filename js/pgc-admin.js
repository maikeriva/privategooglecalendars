(function(win, $) {
  
  win.pgc_on_submit = function() {
    var file = $("#pgc_client_secret");
    if (!file.length) {
      return true;
    }
    file = file[0];
    if (!("files" in file)) {
      return true;
    }
    if (file[0].files.length === 0) {
      alert('Select a file');
      return false;
    }
    return true;
  };

}(this, jQuery));