document.addEventListener('DOMContentLoaded', function () {
    var search = document.querySelector('[data-docsmith-search]');
    var nav = document.querySelector('[data-docsmith-nav]');
    var empty = document.querySelector('[data-docsmith-empty]');

    if (!search || !nav || !empty) {
        return;
    }

    var items = Array.prototype.slice.call(nav.querySelectorAll('[data-nav-item]'));

    var update = function () {
        var query = String(search.value || '').toLowerCase().trim();
        var visible = 0;

        items.forEach(function (item) {
            var title = String(item.getAttribute('data-title') || '').toLowerCase();
            var matches = query === '' || title.indexOf(query) !== -1;

            item.style.display = matches ? '' : 'none';

            if (matches) {
                visible++;
            }
        });

        empty.style.display = visible === 0 ? 'block' : 'none';
    };

    search.addEventListener('input', update);
    update();
});