// Vue instance used by todos
var vm = new Vue ({
    el: '#todos',
    // to support vue in twig
    delimiters: ['[[',']]'],
    data: {
        todos: [],
        form: {
            success: null,
            error: null,
            description: null,
            crsftokentodos: null,
        },
    },
    methods: {
        toggleComplete(todoId) {
            $.ajax({
                type: "PATCH",
                url: '/todo/togglecomplete/' + todoId,
                success: (response) => {
                    if (response.status) {
                        // update the data on DOM
                        index = this.todos.findIndex((todo => todo.id == todoId));
                        this.todos[index].completed = this.todos[index].completed == 1 ? 0 : 1;
                        // update form success message
                        this.form.success = response.success;
                    }
                    else {
                        // update form error message
                        this.form.error = response.error;
                    }
                }
            });
        },
        deleteTodo(todoId) {
            $.ajax({
                type: "DELETE",
                url: '/todo/delete/' + todoId,
                success: (response) => {
                    if (response.status) {
                        // update the data on DOM
                        index = this.todos.findIndex((todo => todo.id == todoId));
                        this.todos.splice(index, 1);
                        // update form success message
                        this.form.success = response.success;
                    }
                    else {
                        // update form error message
                        this.form.error = response.error;
                    }
                },
            });
        },
        addTodo() {

            // dont make it to the server, error now
            if(!this.form.description || !this.form.crsftokentodos){
                this.form.error = 'Please reload the form and check again';
                return;
            }

            var payload = {
                'description': this.form.description,
                'crsftokentodos': this.form.crsftokentodos
            }

            $.ajax({
                type: "POST",
                url: '/todo/add',
                data: payload,
                success: (response) => {
                    if (response.status) {
                        // update the data on DOM
                        this.todos.push(response.lastinsert)
                        // update form success message
                        this.form.success = response.success;
                    }
                    else {
                        // update form error message
                        this.form.error = response.error;
                    }
                },
            });
        }
    },
    created() {
        $.ajax({
            type: "GET",
            url: '/new/todo/0',
            success: (response) => {
                this.todos = response.todos;
                this.form.crsftokentodos = response.form.crsftokentodos;
            },
        });
    }

});