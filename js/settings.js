/**
 * @author Project Seminar "sciebo@Learnweb" of the University of Muenster
 * @copyright Copyright (c) 2017, University of Muenster
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

$(document).ready(function () {
	var elements = document.querySelectorAll('.delete');

	for (var i = 0; i < elements.length; i++) {
		elements[i].addEventListener('submit', function (event) {
			event.preventDefault();
			if (confirm(this.getAttribute('data-confirm'))) {
				this.submit();
			}
		}, false);
	}

	var testToken = Math.random().toString();
	$.ajax({
		type: 'POST',
		url: OC.generateUrl('apps/oauth2/test'),
		headers: {
			'Authorization': 'Bearer ' + testToken
		}
	}).done(function(data){
		if (data.authHeaderFound !== true) {
			OC.Notification.show(
				'Oauth2 will not work properly as your webserver does not pass the Authorization header to PHP.'
			);
		}
	});
});
