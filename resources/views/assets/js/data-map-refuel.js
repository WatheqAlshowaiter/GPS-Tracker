import Ajax from './ajax';
import Feather from './feather';
import LocalStorage from './local-storage';
import Map from './map';
import { dateUtc, dateToIso } from './helper'

(function () {
    'use strict';

    const element = document.querySelector('[data-map-refuels]');

    if (!element || !element.dataset.mapRefuels) {
        return;
    }

    const render = element.querySelector('[data-map-render]');

    if (!render) {
        return;
    }

    let refuels = [];

    try {
        refuels = JSON.parse(element.dataset.mapRefuels)
            .filter(refuel => refuel.position)
            .sort((a, b) => a.date_at > b.date_at ? -1 : 1);
    } catch (e) {
        return;
    }

    const map = new Map(render);

    (function () {
        const mapListToggle = element.querySelector('[data-map-list-toggle]');

        if (!mapListToggle) {
            return;
        }

        const localStorage = new LocalStorage('map-refuel');

        mapListToggle.addEventListener('click', (e) => {
            e.preventDefault();

            const show = element.classList.contains('map-list-hidden');

            element.classList.add('map-list-moving');
            element.classList.toggle('map-list-hidden');

            mapListToggle.innerHTML = show ? '⟼' : '⟻';

            localStorage.set('list', show);

            setTimeout(() => {
                element.classList.remove('map-list-moving');
                map.invalidateSize();
            }, 500);
        });

        if (localStorage.get('list')) {
            mapListToggle.dispatchEvent(new Event('click'));
        }
    })();

    map.setRefuels(refuels);

    const mapPointClick = function (e, point) {
        e.preventDefault();

        map.showMarker(point.dataset.mapPoint);
    };

    map.setListTable(document.querySelector('[data-map-list-table]'));

    if (refuels.length) {
        map.fitBounds();
    }

    document.querySelectorAll('[data-map-point]').forEach(point => {
        point.addEventListener('click', (e) => mapPointClick(e, point));
    });
})();
