define([], function () {
    window.requirejs.config({
        paths: {
           //Enter the paths to your required java-script files
           "jqmask": M.cfg.wwwroot+"/enrol/pagseguro/js/jquery-mask/dist/jquery.mask.min"
        },
        shim: {
           //Enter the "names" that will be used to refer to your libraries
           "jqmask": { exports: "jqmask"}
        }
    });
});

