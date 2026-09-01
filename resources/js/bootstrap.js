import axios from 'axios';

window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.baseURL = '/api';

const token = localStorage.getItem('auth_token');
if (token) {
    axios.defaults.headers.common.Authorization = `Bearer ${token}`;
}

export { axios };
