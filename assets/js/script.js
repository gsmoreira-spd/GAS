/* ============================================================
   SISTEMA ERP - JAVASCRIPT PRINCIPAL
   ============================================================ */

// === TOGGLE SIDEBAR (Mobile) ===
function toggleSidebar() {
    document.querySelector('.sidebar')?.classList.toggle('active');
}

// === MODO ESCURO ===
function toggleDarkMode() {
    document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', document.body.classList.contains('dark') ? '1' : '0');
}
if (localStorage.getItem('darkMode') === '1') {
    document.body.classList.add('dark');
}

// === TOAST (NOTIFICAÇÕES) ===
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// === MODAL ===
function openModal(id) {
    document.getElementById(id)?.classList.add('active');
}
function closeModal(id) {
    document.getElementById(id)?.classList.remove('active');
}
// Fecha modal ao clicar fora
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// === CONFIRMAÇÃO DE EXCLUSÃO ===
function confirmarExclusao(url, nome = 'este registro') {
    if (confirm(`Tem certeza que deseja excluir ${nome}?\n\nEsta ação não pode ser desfeita.`)) {
        window.location.href = url;
    }
}

// === PESQUISA EM TABELA ===
function pesquisarTabela(inputId, tabelaId) {
    const input = document.getElementById(inputId);
    const tabela = document.getElementById(tabelaId);
    if (!input || !tabela) return;
    
    input.addEventListener('keyup', function() {
        const filtro = this.value.toLowerCase();
        const linhas = tabela.getElementsByTagName('tr');
        for (let i = 1; i < linhas.length; i++) {
            const texto = linhas[i].textContent.toLowerCase();
            linhas[i].style.display = texto.includes(filtro) ? '' : 'none';
        }
    });
}

// === FORMATAR MOEDA ===
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

// === MÁSCARA TELEFONE ===
function mascaraTelefone(input) {
    input.value = input.value
        .replace(/\D/g, '')
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2')
        .substring(0, 15);
}

// === MÁSCARA CPF/CNPJ ===
function mascaraCpfCnpj(input) {
    let v = input.value.replace(/\D/g, '');
    if (v.length <= 11) {
        v = v.replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        v = v.replace(/^(\d{2})(\d)/, '$1.$2')
             .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
             .replace(/\.(\d{3})(\d)/, '.$1/$2')
             .replace(/(\d{4})(\d)/, '$1-$2');
    }
    input.value = v.substring(0, 18);
}

// === IMPRIMIR ===
function imprimir(elementoId = null) {
    if (elementoId) {
        const elemento = document.getElementById(elementoId);
        const w = window.open('', '', 'width=900,height=700');
        w.document.write('<html><head><title>Imprimir</title>');
        w.document.write('<link rel="stylesheet" href="../assets/css/style.css">');
        w.document.write('</head><body>' + elemento.innerHTML + '</body></html>');
        w.document.close();
        setTimeout(() => { w.print(); }, 500);
    } else {
        window.print();
    }
}

// === LOADER ===
function showLoader() {
    if (document.getElementById('global-loader')) return;
    const div = document.createElement('div');
    div.id = 'global-loader';
    div.style.cssText = 'position:fixed;inset:0;background:rgba(255,255,255,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;';
    div.innerHTML = '<div style="width:50px;height:50px;border:5px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:spin 1s linear infinite;"></div><style>@keyframes spin{to{transform:rotate(360deg);}}</style>';
    document.body.appendChild(div);
}
function hideLoader() {
    document.getElementById('global-loader')?.remove();
}

// === Auto-fechar alertas ===
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
