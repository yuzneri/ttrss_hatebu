/* global dojo, Plugins, xhr, App, Notify, fox, __ */

Plugins.Hatebu = {
  view: function(id) {
    const dialog = new fox.SingleUseDialog({
      title: 'はてなブックマーク',
      content: __('Loading, please wait...'),
    });

    const tmph = dojo.connect(dialog, 'onShow', function() {
      dojo.disconnect(tmph);

      xhr.post('backend.php', App.getPhArgs('ttrss_hatebu', 'getHatebuInfo', {id: id}), (reply) => {
        dialog.attr('content', reply);
      });
    });

    dialog.show();
  },
};
