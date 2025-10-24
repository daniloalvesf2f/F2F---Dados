// Webpack Imports
import * as bootstrap from 'bootstrap';

(function () {
	'use strict';

	// Focus input if Searchform is empty
	[].forEach.call(document.querySelectorAll('.search-form'), (el) => {
		el.addEventListener('submit', function (e) {
			var search = el.querySelector('input');
			if (search.value.length < 1) {
				e.preventDefault();
				search.focus();
			}
		});
	});

	// Initialize Popovers: https://getbootstrap.com/docs/5.0/components/popovers
	var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
	var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
		return new bootstrap.Popover(popoverTriggerEl, {
			trigger: 'focus',
		});
	});
	
	// Adiciona o loading para os formulários de importação CSV
	document.addEventListener('DOMContentLoaded', function() {
		// Seleciona todos os formulários de importação CSV
		const importForms = document.querySelectorAll('form[action*="admin-post.php"]');
		const loadingOverlay = document.getElementById('f2f-loading-overlay');
		
		console.log('Formulários encontrados:', importForms.length);
		console.log('Overlay encontrado:', loadingOverlay ? 'Sim' : 'Não');
		
		if (importForms && loadingOverlay) {
			importForms.forEach(form => {
				// Verifica se o formulário é de importação (qualquer formulário na página de configurações)
				const actionInput = form.querySelector('input[name="action"]');
				if (actionInput && (
					actionInput.value.startsWith('f2f_import') || 
					actionInput.value === 'f2f_fetch_csv' ||
					actionInput.value === 'f2f_clear_data')) {
					
					console.log('Adicionando evento ao formulário com action:', actionInput.value);
					
					form.addEventListener('submit', function(e) {
						console.log('Formulário enviado:', actionInput.value);
						// Mostra o loading
						loadingOverlay.classList.add('active');
						
						// Desabilita o botão de submit para evitar cliques duplos
						const submitBtn = form.querySelector('input[type="submit"]');
						if (submitBtn) {
							submitBtn.disabled = true;
							submitBtn.value = 'Processando...';
						}
					});
				}
			});
		}
	});
})();
