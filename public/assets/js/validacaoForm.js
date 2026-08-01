//Validação do nome de usuário no formulário de registro
// (a validação de senha e confirmação de senha fica em passwordRequirements.js)

const formulario = document.getElementById('registerForm');

// Validação de Usuário
const username = document.getElementById('username');
const errorUsername = document.getElementById('messageErrorUsername');


formulario.addEventListener('submit', function(event){
    errorUsername.textContent = "";
    if(username.value.trim() === ""){
        event.preventDefault();
        errorUsername.textContent = "O nome de usuário é obrigatório.";
    }else if(username.value.length < 3){
        event.preventDefault();
        errorUsername.textContent = "O nome de usuário deve ter no mínimo 3 caracteres.";
    }else if(username.value.length > 20){
        event.preventDefault();
        errorUsername.textContent = "O nome de usuário deve ter no máximo 20 caracteres.";
    }else if(!/^[a-zA-Z0-9_]+$/.test(username.value)){
        event.preventDefault();
        errorUsername.textContent = "O nome de usuário só pode conter letras, números e underscores.";
    }

});
