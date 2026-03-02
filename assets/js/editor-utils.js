const TiendaGuard = {
  safeQuery(selector) {
    try {
      return document.querySelector(selector);
    } catch (e) {
      console.error(`Error with selector: ${selector}`, e);
      return null;
    }
  },

  apiClient: {
    get(url, params = {}) {
      const urlParams = new URLSearchParams(Object.entries(params));
      const fullUrl = `${url}?${urlParams}`;
      return fetch(fullUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .catch(error => console.error('API GET Error:', error));
    },

    post(url, data = {}) {
      const formData = new URLSearchParams();
      for (const key in data) {
        formData.append(key, data[key]);
      }

      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData,
      })
      .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .catch(error => console.error('API POST Error:', error));
    }
  }
};
