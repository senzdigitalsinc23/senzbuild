// Example starter JavaScript for disabling form submissions if there are invalid fields
(() => {
  'use strict'

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  const forms = document.querySelectorAll('.needs-validation')

  // Loop over them and prevent submission
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()
      }

      form.classList.add('was-validated')
    }, false)
  })
})();


/* --- GET list of Users --- */
const loadBtn = document.getElementById('save-user');
const resultEl = document.getElementById('result');

const alertBox = document.getElementById("alert-box");
const alertMessage = document.getElementById('alert-message');
const btnClose = document.getElementById('btn-close');


//Function for creating new user
document.getElementById("register").addEventListener("submit", function(e) {
 
        e.preventDefault();
        e.stopPropagation();

        time = 5000;

        const form = e.target;
        const formData = new FormData(form);

        fetch('/api/register', { 
            method  : 'POST',
            body    :   formData,
            credentials: 'same-origin'
        })
        .then(response => {        
            //if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
            return response.json();
        })
        .then(data => {console.log(data);        

        if (data.success) {alert()
            alertBox.classList.remove('alert-danger');
            alertBox.classList.add('alert-success');
            alertMessage.innerHTML = data.message
            alertBox.hidden = false;
            btnClose.hidden = false;

            //loadUsers();
            
        } else {           
            
            alertBox.classList.remove('alert-success');
            alertBox.classList.add('alert-danger');
            alertMessage.innerHTML = data.message

            btnClose.hidden = false;
            alertBox.hidden = false;            

        }

        /* setTimeout(() =>{
            btnClose.hidden = true;
            alertBox.hidden = true;
        }, time); */

    return;
    })
    .catch(err => {
        console.error('Fetch error:', err);
        //resultEl.textContent = 'Failed to load students.';
    });
}) 


//Convert first letter of word to uppercase
function firstUpper(val) {
    return String(val).charAt(0).toUpperCase() + String(val).slice(1);
}

//Function for loading users from database
function loadUsers() {
  fetch('/api/admin/users', { method: 'GET', credentials: 'same-origin' })
    .then(response => {
      //if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
      return response.json();
    })
    .then(data => {console.log(data);
    
        tbody = document.getElementById('tbody');

        tbody = document.getElementById('tbody').innerHTML = '';
        
        data = data.message;

        body = '';

        data.forEach(element => {
            body += `<tr id='trow'>
            <td>${element['id']}</td>
            <td>${element['name']}</td>
            <td>${element['email']}</td>
                <td>${firstUpper(element['role_name']) ?? 'NA'}</td>
                <td>
                <a href="/web/admin/users/${element['id']}/edit" class="btn btn-sm btn-warning"><img src="/../assets/images/bootstrap-icons/pencil-square.svg" alt=""></a>
                    <a href="/web/admin/users/${element['id']}/delete" class="btn btn-sm btn-danger"><img src="/../assets/images/bootstrap-icons/unlock-fill.svg" alt=""></a>
                </td>
            </tr>
            `;
        });

        body += `
            <tr>
                <td colspan='5'>
                <nav aria-label="Page navigation example">
                    <ul class="pagination">
                        <li class="page-item">
                        <a class="page-link" href="#" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                        </li>
                        <li class="page-item"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                        <a class="page-link" href="#" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                        </li>
                    </ul>
                </nav>
            </td>
            </tr>
        `
        
        tbody = document.getElementById('tbody').innerHTML = body;
    })
    .catch(err => {
      console.error('Fetch error:', err);
      //resultEl.textContent = 'Failed to load students.';
    });

}

loadUsers();



const btnAddUser = document.getElementById('add-user');
const btnCloseAddUser = document.getElementById('close-adduser');

btnAddUser.addEventListener('click', () => {
    //alert()
    document.getElementById('create-user').hidden = false;
});

btnCloseAddUser.addEventListener('click', () => {
    //alert()
    document.getElementById('create-user').hidden = true;
})

btnClose.hidden = true;
alertBox.hidden = true;

btnClose.addEventListener('click', () => {
    btnClose.hidden = true;
    alertBox.hidden = true;
    alertMessage.innerHTML = ''
})