
jQuery(document).ready(function () {

    jQuery('table.dataTable.columnFilter tfoot th.filter').each(function () {
        var title = jQuery(this).text();
        jQuery(this).html('<div contenteditable="true"></div>');
    });

    jQuery('table.dataTable:not(.serverside)').DataTable({
        pageLength: 25,
        dom: '<<"top"lpif><t><"bottom"lpif>>',
        order: [],
        lengthMenu: [[10, 25, 50, 100, 1000, -1], [10, 25, 50, 100, 1000, "Tous"]],
        language: {
            //url: 'https://cdn.datatables.net/plug-ins/1.10.24/i18n/French.json',
            search: 'Filtrer',
            lengthMenu: '_MENU_',
            info: 'Page _PAGE_ sur _PAGES_ &bull; Résultats _START_ à _END_ sur _TOTAL_',
            paginate: {
                previous: '<',
                next: '>'
            }
        },
        initComplete: function () {
            // Apply the search
            this.api().columns().every(function () {
                var that = this;

                jQuery('div[contenteditable]', this.footer()).on('keyup change clear', function () {
                    if (that.search() !== jQuery(this).text()) {
                        that
                            .search(jQuery(this).text())
                            .draw();
                    }
                });
            });
        }
    });

});