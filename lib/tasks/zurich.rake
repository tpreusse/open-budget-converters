# encoding: UTF-8

namespace :zurich do
  desc "creates zurich json file"
  task :generate_json_from_csv do
    puts "start processing"
    budget = OpenBudget::Budget.new

    budget.load_meta 'data/zurich/meta.json'
    budget.from_zurich_csv 'source/zurich/B13_Institution_Konzernkonto.csv'

    File.open("data/zurich/data.json", 'wb') do |file|
      file.write budget.to_json
    end
    puts "done"
  end
end
