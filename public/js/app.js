/* SIGE Bengo — comportamento base da interface (sem frameworks). */
(function () {
    'use strict';

    var html = document.documentElement;
    var CHAVE = 'sige.sidebar.recolhida';

    // Sidebar recolhível (preferência guardada no navegador)
    try {
        if (localStorage.getItem(CHAVE) === '1') { html.classList.add('sidebar-recolhida'); }
    } catch (e) { /* armazenamento indisponível */ }

    document.addEventListener('click', function (ev) {
        var botao = ev.target.closest('[data-sige-toggle="sidebar"]');
        if (botao) {
            if (window.matchMedia('(max-width: 991.98px)').matches) {
                html.classList.toggle('sidebar-aberta');
            } else {
                html.classList.toggle('sidebar-recolhida');
                try { localStorage.setItem(CHAVE, html.classList.contains('sidebar-recolhida') ? '1' : '0'); } catch (e) {}
            }
            return;
        }
        if (ev.target.classList.contains('sige-sombra')) { html.classList.remove('sidebar-aberta'); }
    });

    document.addEventListener('DOMContentLoaded', function () {
        // Toasts de sessão
        document.querySelectorAll('.toast').forEach(function (el) {
            new bootstrap.Toast(el, { delay: 6000 }).show();
        });

        // Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });

        // Confirmação genérica: <form data-confirmar="Mensagem"> abre o modal de confirmação
        var modalEl = document.getElementById('modalConfirmacao');
        var formPendente = null;
        if (modalEl) {
            var modal = new bootstrap.Modal(modalEl);
            document.addEventListener('submit', function (ev) {
                var form = ev.target;
                if (!form.dataset.confirmar || form.dataset.confirmado === '1') { return; }
                ev.preventDefault();
                formPendente = form;
                modalEl.querySelector('[data-mensagem]').textContent = form.dataset.confirmar;
                var btn = modalEl.querySelector('[data-confirmar-btn]');
                btn.className = 'btn ' + (form.dataset.confirmarClasse || 'btn-primary');
                btn.textContent = form.dataset.confirmarTexto || 'Confirmar';
                modal.show();
            });
            modalEl.querySelector('[data-confirmar-btn]').addEventListener('click', function () {
                if (formPendente) { formPendente.dataset.confirmado = '1'; modal.hide(); formPendente.submit(); }
            });
        }

        // Evitar duplo envio de formulários
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (ev.defaultPrevented) { return; }
            // Desactivar no ciclo seguinte: o botão que submeteu ainda envia o seu name/value.
            setTimeout(function () {
                form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (b) {
                    b.disabled = true;
                    if (b.dataset.aCarregar) { b.innerHTML = b.dataset.aCarregar; }
                });
            }, 0);
        });

        // Modais de criação/edição (<x-modal-form>)
        document.addEventListener('show.bs.modal', function (ev) {
            var form = ev.target.querySelector('form[data-form-modal]');
            if (!form || !ev.relatedTarget) { return; }
            var btn = ev.relatedTarget;
            var dados = btn.dataset.preencher ? JSON.parse(btn.dataset.preencher) : null;
            form.reset();
            form.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea').forEach(function (el) {
                el.value = el.dataset.padrao !== undefined ? el.dataset.padrao : '';
            });
            form.querySelectorAll('input[type=checkbox]').forEach(function (el) { el.checked = el.dataset.padrao === '1'; });
            form.action = btn.dataset.accao || form.dataset.accaoCriar;
            form.querySelector('input[name=_method]').value = dados ? 'PUT' : 'POST';
            form.querySelector('input[name=_form_accao]').value = form.action;
            var titulo = ev.target.querySelector('[data-titulo-modal]');
            if (titulo) { titulo.textContent = dados ? form.dataset.tituloEditar : form.dataset.tituloCriar; }
            form.querySelectorAll('[data-so-criacao]').forEach(function (el) { el.hidden = !!dados; });
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
            if (dados) {
                Object.keys(dados).forEach(function (k) {
                    // Listas: marca as checkboxes "campo[]" cujos valores constam da lista.
                    if (Array.isArray(dados[k])) {
                        var valores = dados[k].map(String);
                        form.querySelectorAll('[name="' + k + '[]"]').forEach(function (el) { el.checked = valores.indexOf(el.value) >= 0; });
                        return;
                    }
                    form.querySelectorAll('[name="' + k + '"]').forEach(function (el) {
                        if (el.type === 'checkbox') { el.checked = !!dados[k] && dados[k] !== '0'; }
                        else { el.value = dados[k] === null || dados[k] === undefined ? '' : dados[k]; }
                    });
                });
            }
            form.querySelectorAll('select').forEach(function (s) { s.dispatchEvent(new Event('change')); });
        });
        document.querySelectorAll('form[data-form-modal][data-reabrir]').forEach(function (form) {
            new bootstrap.Modal(form.closest('.modal')).show();
        });

        // Filtros: submeter automaticamente ao alterar selects marcados
        document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
            el.addEventListener('change', function () { el.form.submit(); });
        });
    });
})();
