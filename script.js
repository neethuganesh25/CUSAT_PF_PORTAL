function showError(msg) {
    const err = document.getElementById('err');
    err.hidden = !msg;
    err.textContent = msg;
}

// Login form: PF number + date of birth
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
        const pf = document.getElementById('pf_no');
        const dob = document.getElementById('dob');
        let msg = '';

        pf.value = pf.value.trim();
        if (!pf.value) msg = 'Please enter your PF number.';
        else if (!/^\d+$/.test(pf.value)) msg = 'PF number can contain digits only.';
        else if (!/^[089]/.test(pf.value)) msg = 'Contract staff PF numbers start with 0; permanent staff PF numbers start with 8 or 9.';
        else if (!dob.value) msg = 'Please enter your date of birth.';

        showError(msg);
        if (msg) e.preventDefault();
    });
}

// Year filter form (after login)
const searchForm = document.getElementById('searchForm');
if (searchForm) {
    searchForm.addEventListener('submit', function (e) {
        const from = document.getElementById('from').value;
        const to = document.getElementById('to').value;
        const msg = (from && to && Number(from) > Number(to)) ? '"From year" cannot be after "To year".' : '';
        showError(msg);
        if (msg) e.preventDefault();
    });
}

// Login page: if the browser restores the page from its Back/Forward cache, empty the fields again.
window.addEventListener('pageshow', function (e) {
    const f = document.getElementById('loginForm');
    if (f && e.persisted) { f.reset(); }
});
