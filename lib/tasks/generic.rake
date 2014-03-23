# encoding: UTF-8

desc "creates json file from generic csv"
task :generate_json_from_csv, [:file] do |t, args|
    file = args.file
    budget = OpenBudget::Budget.new
    budget.load_csv file
    budget.save_pretty_json "#{file.chomp(File.extname(file))}.json"
end
