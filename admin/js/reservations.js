jQuery(document).ready(function($) {
    var packages = (window.TP_RESERVATIONS && TP_RESERVATIONS.packages) || [];
    var $package = $('#tp_res_package');
    var $departure = $('#tp_res_departure');
    var $filterPackage = $('#tp-res-filter-package');
    var $activeOnly = $('#tp-res-active-only');

    if ( $filterPackage.length && $activeOnly.length ) {
        $filterPackage.on('change', function() {
            if ( $(this).val() !== '0' ) {
                $activeOnly.prop('checked', true);
            }
        });
    }

    if ( ! $package.length || ! $departure.length ) {
        return;
    }

    function findPackage(id) {
        id = parseInt(id, 10) || 0;
        for (var i = 0; i < packages.length; i++) {
            if (parseInt(packages[i].id, 10) === id) {
                return packages[i];
            }
        }
        return null;
    }

    function fillDepartures(selectedUid) {
        var pkg = findPackage($package.val());
        $departure.empty();

        if ( ! pkg || ! pkg.departures.length ) {
            $departure.append('<option value="">Sin salidas cargadas</option>');
            return;
        }

        $departure.append('<option value="">-- Seleccionar salida --</option>');
        pkg.departures.forEach(function(dep) {
            var option = $('<option>').val(dep.uid).text(dep.label);
            if (selectedUid && dep.uid === selectedUid) {
                option.prop('selected', true);
            }
            $departure.append(option);
        });
    }

    function updatePax() {
        var adults = parseInt($('#tp_res_adults').val(), 10) || 0;
        var children = parseInt($('#tp_res_children').val(), 10) || 0;
        $('#tp_res_pax_total').text(adults + children);
    }

    $package.on('change', function() {
        fillDepartures('');
    });

    $(document).on('input change', '#tp_res_adults, #tp_res_children', updatePax);

    fillDepartures($departure.data('selected') || '');
    updatePax();
});
